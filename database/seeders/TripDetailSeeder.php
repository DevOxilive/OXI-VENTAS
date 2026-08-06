<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripDetailSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('trip_details')->updateOrInsert(
            ['id' => 1],
            [
                'trip_id' => 1,
                'cargo_description' => 'Reposición de bebidas, químicos y productos por caja',
                'quantity' => 10,
                'weight' => 120.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
