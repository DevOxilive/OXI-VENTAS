<?php

namespace Database\Seeders;

use App\Support\SystemPermission;
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
            'departments.view',
            'departments.create',
            'departments.update',
            'departments.delete',
            'positions.view',
            'positions.create',
            'positions.update',
            'positions.delete',

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

            // Asistencias
            'attendance.view',
            'attendance.register',
            'attendance.corrections.request',
            'attendance.manage',
            'attendance.corrections.review',
            'attendance.reports',

            // Administración global
            SystemPermission::BRANCHES_ACCESS_ALL,
            ...SystemPermission::exclusive(),

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
            'inventory.purchase-orders.costs',
            'inventory.purchase-orders.generate.view',
            'inventory.purchase-orders.generate.create',
            'inventory.purchase-orders.generate.update',
            'inventory.purchase-orders.generate.transfer',
            'inventory.purchase-orders.purchasing.view',
            'inventory.purchase-orders.completed.view',
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
            'inventory.purchase-reports.view',
            'inventory.purchase-reports.create',
            'inventory.purchase-reports.update',
            'inventory.purchase-reports.delete',

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
