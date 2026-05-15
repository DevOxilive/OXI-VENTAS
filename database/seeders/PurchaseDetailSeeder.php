<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseDetailSeeder extends Seeder
{
    public function run(): void
    {
        $details = [
            ['id' => 1, 'purchase_id' => 1, 'product_id' => 1, 'lot_id' => 1, 'quantity' => 50, 'unit_cost' => 12.00, 'subtotal' => 600.00],
        ];

        foreach ($details as $detail) {
            DB::table('purchase_details')->updateOrInsert(
                ['id' => $detail['id']],
                array_merge($detail, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}