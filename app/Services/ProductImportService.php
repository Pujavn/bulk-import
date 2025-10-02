<?php


namespace App\Services;


use App\Models\Product;
use App\Models\Upload;
use App\Models\Image;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class ProductImportService
{
    /**
     * CSV columns required:
     * sku, name, price, description (optional), image_filename (optional)
     * - image_filename is looked up under $imagesDir (storage/app/<dir>) if provided
     */
    public function import(string $csvFullPath, ?string $imagesDir = null): array
    {
        $handle = fopen($csvFullPath, 'r');
        if (!$handle) throw new \RuntimeException('CSV not readable');


        $header = fgetcsv($handle);
        $required = ['sku', 'name', 'price'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                // Treat as fully invalid file – but still return summary
                return [
                    'total' => 0,
                    'imported' => 0,
                    'updated' => 0,
                    'invalid' => 0,
                    'duplicates' => 0,
                    'errors' => ["Missing required column: $col"],
                ];
            }
        }
        $idx = array_flip($header);


        $total = $imported = $updated = $invalid = 0;
        $updatedCsv = 0;
        $duplicates = 0;
        $errors = [];
        $seenInFile = [];


        // process in chunks to handle large files
        $batch = [];
        $BATCH_SIZE = 1000;


        while (($row = fgetcsv($handle)) !== false) {
            $total++;
            $sku = trim($row[$idx['sku']] ?? '');
            $name = trim($row[$idx['name']] ?? '');
            $price = $row[$idx['price']] ?? null;
            $desc = $row[$idx['description']] ?? null;
            // $imgFile = $row[$idx['image_filename']] ?? null;
            $imgFile = null;
            if (isset($idx['image_filename'])) {
                $col = $idx['image_filename'];
                $imgFile = $row[$col] ?? null;
            }


            // invalid row rules
            if ($sku === '' || $name === '' || !is_numeric($price)) {
                $invalid++;
                continue;
            }


            // duplicates within CSV (same SKU repeats). Count and keep last occurrence.
            if (isset($seenInFile[$sku])) {
                $duplicates++;
                $updatedCsv++;
            }
            $seenInFile[$sku] = true;


            $batch[$sku] = [
                'sku' => $sku,
                'name' => $name,
                'price' => (float) $price,
                'description' => $desc,
                // primary_image_id handled after upsert if image present
                'image_filename' => $imgFile,
            ];


            if (count($batch) >= $BATCH_SIZE) {
                $this->flushBatch($batch, $imagesDir, $imported, $updated, $errors);
                $batch = [];
            }
        }
        fclose($handle);


        if ($batch) {
            $this->flushBatch($batch, $imagesDir, $imported, $updated, $errors);
        }

        $updated += $updatedCsv;
        return compact('total', 'imported', 'updated', 'invalid', 'duplicates', 'errors');
    }

    private function flushBatch(array $batch, ?string $imagesDir, int &$imported, int &$updated, array &$errors): void
    {
        // Upsert-by-SKU (name, price, description)
        $now = now();
        $rows = collect($batch)->values()->map(fn($r) => [
            'sku' => $r['sku'],
            'name' => $r['name'],
            'price' => $r['price'],
            'description' => $r['description'],
            'updated_at' => $now,
            'created_at' => $now,
        ])->all();


        // Determine which exist to compute imported vs updated
        $skus = array_keys($batch);
        $existing = Product::whereIn('sku', $skus)->pluck('id', 'sku');


        DB::transaction(function () use ($rows) {
            Product::upsert($rows, ['sku'], ['name', 'price', 'description', 'updated_at']);
        });


        // Refresh and compute counts
        $after = Product::whereIn('sku', $skus)->pluck('id', 'sku');
        foreach ($skus as $sku) {
            if (!isset($existing[$sku])) $imported++;
            else $updated++;
        }


        // Handle images if provided via image_filename column
        if ($imagesDir) {
            foreach ($batch as $sku => $r) {
                if (!empty($r['image_filename'])) {
                    try {
                        $this->attachLocalImage($sku, $imagesDir, $r['image_filename']);
                    } catch (\Throwable $e) {
                        $errors[] = "Image attach failed for SKU {$sku}: " . $e->getMessage();
                    }
                }
            }
        }
    }
    private function attachLocalImage(string $sku, string $imagesDir, string $filename): void
    {
        $product = Product::where('sku', $sku)->lockForUpdate()->firstOrFail();


        $full = storage_path('app/' . trim($imagesDir, '/') . '/' . $filename);
        if (!is_file($full)) throw new \RuntimeException('File not found: ' . $filename);


        $sha256 = hash_file('sha256', $full);
        $mime = mime_content_type($full) ?: 'application/octet-stream';
        $disk = 'public';
        $dest = 'uploads/originals/' . $sha256 . '.bin';


        if (!Storage::disk($disk)->exists($dest)) {
            Storage::disk($disk)->put($dest, file_get_contents($full));
        }


        // Create Image + Variants via service
        $ingest = app(ImageIngestService::class);
        $image = $ingest->ensureImageFromStored($disk, $dest, $sha256, $mime);


        if ((int)$product->primary_image_id === (int)$image->id) return; // no-op


        $product->update(['primary_image_id' => $image->id]);
    }
}
