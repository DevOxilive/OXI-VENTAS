<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            [
                'id' => 1,
                'name' => 'Manager',
                'description' => 'GeneralManager',
                'department_id' => 1,
                'active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Cashier',
                'description' => 'SalesOperator',
                'department_id' => 2,
                'active' => true,
            ],
            [
                'id' => 3,
                'name' => 'InventoryManager',
                'description' => 'WarehouseManager',
                'department_id' => 3,
                'active' => true,
            ],
        ];

        foreach ($positions as $position) {
            DB::table('positions')->updateOrInsert(
                ['id' => $position['id']],
                array_merge($position, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}