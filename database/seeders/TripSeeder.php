<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        $employeeId = DB::table('employees')->value('id') ?? 1;

        DB::table('trips')->updateOrInsert(
            ['id' => 1],
            [
                'departure_date' => now(),
                'arrival_date' => null,
                'employee_id' => $employeeId,
                'vehicle_id' => 1,
                'destination' => 'MainBranch',
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}