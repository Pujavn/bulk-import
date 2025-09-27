<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BigCsvSeeder extends Seeder
{
    public function run(): void
    {
        $rows = 10050; // > 10k
        $path = storage_path('app/imports/big_products.csv');
        @mkdir(dirname($path), 0777, true);
        $fh = fopen($path, 'w');
        fputcsv($fh, ['sku','name','price','description','image_filename']);
        for ($i=1; $i <= $rows; $i++) {
            $sku = 'SKU'.str_pad((string)$i, 5, '0', STR_PAD_LEFT);
            $name = 'Item '.$i;
            $price = number_format(mt_rand(1000, 99999)/100, 2, '.', '');
            $desc = 'Mock row '.$i;
            $img  = ($i % 5 === 0) ? 'sample'.($i % 20).'.jpg' : '';
            fputcsv($fh, [$sku,$name,$price,$desc,$img]);
        }
        fclose($fh);

        $this->command->info('CSV generated at storage/app/imports/big_products.csv');
    }
}
