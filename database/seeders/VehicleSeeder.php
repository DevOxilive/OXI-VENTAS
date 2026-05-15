<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            ['id' => 1, 'plate' => 'ABC1234', 'model' => 'NissanNP300', 'capacity' => 1500.00, 'active' => true],
            ['id' => 2, 'plate' => 'XYZ5678', 'model' => 'FordTransit', 'capacity' => 3000.00, 'active' => true],
        ];

        foreach ($vehicles as $vehicle) {
            DB::table('vehicles')->updateOrInsert(
                ['id' => $vehicle['id']],
                array_merge($vehicle, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}