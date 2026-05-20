<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BranchInventorySeeder extends Seeder
{
    public function run(): void
    {
        $branches = DB::table('branches')->get();
        $products = DB::table('products')->get();

        foreach ($branches as $branch) {
            foreach ($products as $product) {
                DB::table('branch_inventory')->updateOrInsert(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'current_stock' => rand(5, 80),
                        'minimum_stock' => 10,
                        'maximum_stock' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}