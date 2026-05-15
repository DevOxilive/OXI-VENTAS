<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['id' => 1, 'name' => 'CocaCola600ML', 'description' => 'SoftDrink600ML', 'price' => 18.00, 'category_id' => 1, 'active' => true],
            ['id' => 2, 'name' => 'DoritosNacho', 'description' => 'SnackChips', 'price' => 22.00, 'category_id' => 2, 'active' => true],
            ['id' => 3, 'name' => 'MilkOneLiter', 'description' => 'DairyProduct', 'price' => 28.00, 'category_id' => 3, 'active' => true],
            ['id' => 4, 'name' => 'FabulosoOneLiter', 'description' => 'CleaningProduct', 'price' => 35.00, 'category_id' => 4, 'active' => true],
        ];

        foreach ($products as $product) {
            DB::table('products')->updateOrInsert(
                ['id' => $product['id']],
                array_merge($product, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}