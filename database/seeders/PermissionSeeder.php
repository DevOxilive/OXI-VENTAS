<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Inicio
            'dashboard.executive.view',

            // Capital Humano
            'employees.view',
            'employees.create',
            'employees.update',
            'employees.delete',

            // Sistemas
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'systems.tickets.view',
            'systems.tickets.update',
            'systems.cash-closure-tickets.view',
            'systems.cash-closure-tickets.update',
            'systems.labels.view',
            'systems.labels.update',

            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
            'sales.reports',
            'sales.cash-closures.view',
            'sales.cash-closures.create',
            'sales.cash-closures.update',
            'sales.cash-closures.delete',

            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',
            'branches.view',
            'branches.create',
            'branches.update',
            'branches.delete',
            'files.export',

            // Sucursales
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
            'inventory.view',
            'inventory.create',
            'inventory.update',
            'inventory.delete',

            // Ventas
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
            'sales.reports',
            'sales.cash-closures.view',
            'sales.cash-closures.create',
            'sales.cash-closures.update',
            'sales.cash-closures.delete',

            // Impresoras
            'systems.tickets.view',
            'systems.tickets.update',
            'systems.cash-closure-tickets.view',
            'systems.cash-closure-tickets.update',
            'systems.labels.view',
            'systems.labels.update',
        ];

        foreach (array_unique($permissions) as $permission) {
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
