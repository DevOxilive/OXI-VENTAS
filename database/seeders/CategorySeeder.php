<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['id' => 1, 'name' => 'Drinks', 'active' => true],
            ['id' => 2, 'name' => 'Snacks', 'active' => true],
            ['id' => 3, 'name' => 'Dairy', 'active' => true],
            ['id' => 4, 'name' => 'Cleaning', 'active' => true],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['id' => $category['id']],
                array_merge($category, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}