<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Administración', 'active' => true],
            ['name' => 'Ventas', 'active' => true],
            ['name' => 'Inventario', 'active' => true],
            ['name' => 'Logística', 'active' => true],
            ['name' => 'Sistemas', 'active' => true],
            ['name' => 'Recursos Humanos', 'active' => true],
        ];

        foreach ($departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['name' => $department['name']],
                array_merge($department, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
