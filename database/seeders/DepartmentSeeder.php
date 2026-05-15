<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['id' => 1, 'name' => 'Management', 'area_id' => 1, 'active' => true],
            ['id' => 2, 'name' => 'PointOfSale', 'area_id' => 2, 'active' => true],
            ['id' => 3, 'name' => 'Warehouse', 'area_id' => 3, 'active' => true],
            ['id' => 4, 'name' => 'Distribution', 'area_id' => 4, 'active' => true],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['id' => $department['id']],
                array_merge($department, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}