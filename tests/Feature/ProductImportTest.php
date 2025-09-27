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
    public function it_upserts_by_sku_and_reports_summary()
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
}
