<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('areas')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Administration',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('areas')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Sales',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('areas')->updateOrInsert(
            ['id' => 3],
            [
                'name' => 'Inventory',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('areas')->updateOrInsert(
            ['id' => 4],
            [
                'name' => 'Logistics',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}