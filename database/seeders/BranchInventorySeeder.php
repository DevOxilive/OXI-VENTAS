<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchInventorySeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('branches')->value('id') ?? 1;

        $items = [
            ['id' => 1, 'branch_id' => $branchId, 'product_id' => 1, 'current_stock' => 50, 'minimum_stock' => 10, 'maximum_stock' => 100],
            ['id' => 2, 'branch_id' => $branchId, 'product_id' => 2, 'current_stock' => 40, 'minimum_stock' => 10, 'maximum_stock' => 100],
            ['id' => 3, 'branch_id' => $branchId, 'product_id' => 3, 'current_stock' => 30, 'minimum_stock' => 8, 'maximum_stock' => 80],
            ['id' => 4, 'branch_id' => $branchId, 'product_id' => 4, 'current_stock' => 25, 'minimum_stock' => 5, 'maximum_stock' => 60],
        ];

        foreach ($items as $item) {
            DB::table('branch_inventory')->updateOrInsert(
                ['id' => $item['id']],
                array_merge($item, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}