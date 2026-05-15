<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryMovementSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('sucursales')->value('id') ?? 1;

        $movements = [
            ['id' => 1, 'branch_id' => $branchId, 'product_id' => 1, 'lot_id' => 1, 'type' => 'entry', 'quantity' => 50, 'date' => now(), 'reference' => 'PURCHASE001'],
            ['id' => 2, 'branch_id' => $branchId, 'product_id' => 2, 'lot_id' => 2, 'type' => 'entry', 'quantity' => 40, 'date' => now(), 'reference' => 'INITIALSTOCK'],
            ['id' => 3, 'branch_id' => $branchId, 'product_id' => 3, 'lot_id' => 3, 'type' => 'entry', 'quantity' => 30, 'date' => now(), 'reference' => 'INITIALSTOCK'],
            ['id' => 4, 'branch_id' => $branchId, 'product_id' => 1, 'lot_id' => 1, 'type' => 'sale', 'quantity' => 1, 'date' => now(), 'reference' => 'SALE001'],
        ];

        foreach ($movements as $movement) {
            DB::table('inventory_movements')->updateOrInsert(
                ['id' => $movement['id']],
                array_merge($movement, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}