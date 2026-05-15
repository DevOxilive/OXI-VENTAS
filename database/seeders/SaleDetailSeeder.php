<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleDetailSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('sale_details')->updateOrInsert(
            ['id' => 1],
            [
                'sale_id' => 1,
                'product_id' => 1,
                'barcode_id' => 1,
                'lot_id' => 1,
                'quantity' => 1,
                'unit_price' => 18.00,
                'subtotal' => 18.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}