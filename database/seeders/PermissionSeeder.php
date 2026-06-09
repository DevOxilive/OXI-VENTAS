<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',

            'files.export',

            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}