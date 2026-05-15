<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryLotSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('branches')->value('id');

        $lots = [
            ['id' => 1, 'branch_id' => $branchId, 'product_id' => 1, 'lot_code' => 'LOTCOCA001', 'entry_date' => now()->toDateString(), 'expiration_date' => now()->addMonths(8)->toDateString(), 'current_stock' => 50, 'unit_cost' => 12.00, 'active' => true],
            ['id' => 2, 'branch_id' => $branchId, 'product_id' => 2, 'lot_code' => 'LOTDORI001', 'entry_date' => now()->toDateString(), 'expiration_date' => now()->addMonths(6)->toDateString(), 'current_stock' => 40, 'unit_cost' => 15.00, 'active' => true],
            ['id' => 3, 'branch_id' => $branchId, 'product_id' => 3, 'lot_code' => 'LOTMILK001', 'entry_date' => now()->toDateString(), 'expiration_date' => now()->addDays(20)->toDateString(), 'current_stock' => 30, 'unit_cost' => 20.00, 'active' => true],
            ['id' => 4, 'branch_id' => $branchId, 'product_id' => 4, 'lot_code' => 'LOTFAB001', 'entry_date' => now()->toDateString(), 'expiration_date' => now()->addYears(2)->toDateString(), 'current_stock' => 25, 'unit_cost' => 25.00, 'active' => true],
        ];

        foreach ($lots as $lot) {
            DB::table('inventory_lots')->updateOrInsert(
                ['id' => $lot['id']],
                array_merge($lot, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}