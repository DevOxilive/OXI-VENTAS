<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        Branch::insert([
            [
                'name' => 'Lago',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Ajusco',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Diana',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cecilia',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}