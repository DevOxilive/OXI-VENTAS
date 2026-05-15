<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncidentSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('incidents')->updateOrInsert(
            ['id' => 1],
            [
                'trip_id' => 1,
                'description' => 'NoIncidentsReported',
                'date' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}