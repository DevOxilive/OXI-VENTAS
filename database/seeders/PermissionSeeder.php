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

            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'systems.tickets.view',
            'systems.tickets.update',
            'systems.labels.view',
            'systems.labels.update',

            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
            'sales.reports',

            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',

            'inventory.products.view',
            'inventory.products.create',
            'inventory.products.update',
            'inventory.products.delete',

            'inventory.branches.view',
            'inventory.branches.create',
            'inventory.branches.update',
            'inventory.branches.delete',

            'inventory.purchase-reports.view',
            'inventory.purchase-reports.create',
            'inventory.purchase-reports.update',
            'inventory.purchase-reports.delete',

            'audits.physical-counts.view',
            'audits.physical-counts.count',
            'audits.physical-counts.reports',
            'audits.physical-counts.view-stock',
            'audits.physical-counts.create',
            'audits.physical-counts.update',
            'audits.physical-counts.delete',
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
