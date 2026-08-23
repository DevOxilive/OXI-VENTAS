<?php

namespace Database\Seeders;

use App\Support\SystemPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $rows = collect(self::catalog())
            ->map(fn (string $permission) => [
                'name' => $permission,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if (DB::table('permissions')->doesntExist()) {
            DB::table('permissions')->insert($rows->all());
            return;
        }

        $existing = DB::table('permissions')
            ->whereIn('name', $rows->pluck('name')->all())
            ->pluck('name')
            ->all();

        $missing = $rows->reject(fn (array $permission) => in_array($permission['name'], $existing, true));

        if ($missing->isNotEmpty()) {
            DB::table('permissions')->insert($missing->values()->all());
        }

        DB::table('permissions')
            ->whereIn('name', $rows->pluck('name')->all())
            ->update(['updated_at' => $now]);
    }

    public static function catalog(): array
    {
        return array_values(array_unique([
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
            // Asistencias
            'attendance.view',
            'attendance.register',
            'attendance.corrections.request',
            'attendance.manage',
            'attendance.corrections.review',
            'attendance.export.excel',
            'attendance.export.pdf',
            'attendance.schedules.view',
            'attendance.schedules.create',
            'attendance.schedules.update',
            'attendance.schedules.delete',
            'attendance.schedule-assignments.view',
            'attendance.schedule-assignments.create',
            'attendance.schedule-assignments.update',
            'attendance.schedule-assignments.delete',
            'attendance.incidents.view',
            'attendance.incidents.create',
            'attendance.incidents.update',
            'attendance.incidents.delete',
            'attendance.incidents.approve',
            'attendance.incidents.reject',

            // Administración global
            SystemPermission::BRANCHES_ACCESS_ALL,
            ...SystemPermission::exclusive(),
            SystemPermission::SETTINGS_MANAGE,
            SystemPermission::INTEGRATIONS_MANAGE,
            SystemPermission::TOOLS_ACCESS,
            SystemPermission::MONITORING_VIEW,
            SystemPermission::STATISTICS_VIEW,
            SystemPermission::LOGS_VIEW,
            SystemPermission::MAINTENANCE_MANAGE,
            SystemPermission::RECORDS_VIEW_ALL,

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
            'inventory.branches.stock-in',
            'inventory.branches.stock-out',
            'inventory.branches.stock-adjust',
            'inventory.branches.batches.update',
            'inventory.branches.config.update',
            'inventory.purchase-orders.source.view',
            'inventory.purchase-orders.source.update',
            'inventory.purchase-orders.source.review',
            'inventory.purchase-orders.source.transfer',
            'inventory.purchase-orders.general.view',
            'inventory.purchase-orders.general.create',
            'inventory.purchase-orders.general.update',
            'inventory.purchase-orders.general.complete',
            'audits.physical-counts.count',
            'audits.physical-counts.view-stock',
            'audits.physical-counts.create',
            'audits.physical-counts.close',
            'audits.physical-counts.reopen',
            'audits.physical-counts.finalize',
            'audits.physical-counts.participants',
            'audits.physical-counts.apply',
            'audits.physical-counts.delete',

            // Ventas
            'sales.view',
            'sales.create',
            'sales.update',
            'sales.delete',
            'sales.reports',
            'sales.employee-credit.view',
            'sales.employee-credit.create',
            'sales.employee-credit.collect',
            'sales.cash-closures.view',
            'sales.cash-closures.create',
            'sales.cash-closures.update',
            'sales.cash-closures.delete',
            'sales.purchase-lists.view',
            'sales.purchase-lists.create',
            'sales.purchase-lists.update',
            'sales.purchase-lists.delete',
            'sales.purchase-orders.view',
            'sales.purchase-orders.receive',

            // Reportes
            'reports.sales.view',
            'reports.sales.export.excel',
            'reports.sales.export.pdf',
            'reports.audits.view',
            'reports.audits.export.excel',
            'reports.audits.export.pdf',
            'reports.cash-closures.view',
            'reports.cash-closures.create',
            'reports.cash-closures.update',
            'reports.cash-closures.delete',
            'reports.inventory.view',
            'reports.inventory.export.excel',
            'reports.inventory.export.pdf',
            'reports.movements.view',
            'reports.movements.export.excel',
            'reports.movements.export.pdf',

            // Impresoras
            'systems.tickets.view',
            'systems.tickets.update',
            'systems.cash-closure-tickets.view',
            'systems.cash-closure-tickets.update',
            'systems.labels.view',
            'systems.labels.update',
            'systems.labels.print',
            'systems.qz.sign',
        ]));
    }
}
