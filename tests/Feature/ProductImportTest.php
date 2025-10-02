<?php


namespace Tests\Feature;


// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\ProductImportService;
use App\Models\Product;


class ProductImportTest extends TestCase
{
    // use RefreshDatabase;


    /** @test */
    public function test_it_upserts_by_sku_and_reports_summary()
    {
        // Create a temp CSV with duplicates + invalid rows
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents(
            $csv,
            implode(",", ['sku', 'name', 'price', 'description']) . "\n" .
                "A001,Alpha,10.00,First\n" .
                "A002,Beta,not_a_number,Oops\n" . // invalid price
                "A001,Alpha-Updated,12.50,Updated\n" // duplicate SKU in same file
        );


        $service = app(ProductImportService::class);
        $summary = $service->import($csv, null);


        $this->assertSame(3, $summary['total']);
        $this->assertSame(1, $summary['invalid']); // Beta row invalid
        $this->assertSame(1, $summary['duplicates']); // A001 duplicated once
        $this->assertSame(1, $summary['imported']); // A001 (first occurrence)
        $this->assertSame(1, $summary['updated']); // A001 (updated by last occurrence)


        // Verify DB state reflects the last occurrence (upsert-by-SKU)
        $p = Product::where('sku', 'A001')->first();
        $this->assertNotNull($p);
        $this->assertSame('Alpha-Updated', $p->name);
        $this->assertEquals(12.50, (float)$p->price);
    }

    /** @test */
    public function test_imports_and_attaches_images_from_csv_existing_images()
    {
        // Use SKUs unlikely to collide with your data
        $rows = [
            ['sku' => 'X001', 'name' => 'WithImage1', 'price' => '11.00', 'description' => 'ok', 'image_filename' => 'demo1.jpg'],
            ['sku' => 'X002', 'name' => 'WithImage2', 'price' => '12.00', 'description' => 'ok', 'image_filename' => 'demo2.jpg'],
            ['sku' => 'X003', 'name' => 'WithImage3', 'price' => '13.00', 'description' => 'ok', 'image_filename' => 'demo3.jpg'],
        ];

        // Make a temp CSV (with image_filename column)
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        $fp  = fopen($csv, 'w');
        fputcsv($fp, ['sku', 'name', 'price', 'description', 'image_filename']);
        foreach ($rows as $r) fputcsv($fp, $r);
        fclose($fp);

        // Run import pointing at storage/app/import_images
        $service = app(\App\Services\ProductImportService::class);
        $summary = $service->import($csv, 'import_images');

        // Summary expectations
        $this->assertSame(count($rows), $summary['total']);
        $this->assertSame(count($rows), $summary['imported']);
        $this->assertSame(0, $summary['updated']);
        $this->assertSame(0, $summary['invalid']);
        $this->assertSame(0, $summary['duplicates']);
        $this->assertTrue(empty($summary['errors'] ?? []), 'Import reported errors: ' . json_encode($summary['errors'] ?? []));

        // DB assertions: products exist and have primary images
        foreach ($rows as $r) {
            $p = \App\Models\Product::where('sku', $r['sku'])->first();
            $this->assertNotNull($p, "Product not found: {$r['sku']}");
            $this->assertNotNull($p->primary_image_id, "No primary image for: {$r['sku']}");
        }
    }
}
