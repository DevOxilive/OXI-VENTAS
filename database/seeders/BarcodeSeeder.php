<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarcodeSeeder extends Seeder
{
    public function run(): void
    {
        $barcodes = [
            ['id' => 1, 'product_id' => 1, 'code' => '7501055300023', 'type' => 'EAN13', 'base_quantity' => 1, 'active' => true],
            ['id' => 2, 'product_id' => 2, 'code' => '7501011134567', 'type' => 'EAN13', 'base_quantity' => 1, 'active' => true],
            ['id' => 3, 'product_id' => 3, 'code' => '7501020512345', 'type' => 'EAN13', 'base_quantity' => 1, 'active' => true],
            ['id' => 4, 'product_id' => 4, 'code' => '7501031312348', 'type' => 'EAN13', 'base_quantity' => 1, 'active' => true],
        ];

        foreach ($barcodes as $barcode) {
            DB::table('barcodes')->updateOrInsert(
                ['id' => $barcode['id']],
                array_merge($barcode, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}