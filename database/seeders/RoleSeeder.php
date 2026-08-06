<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = [
            'Super Administrador',
            'Administrador',
            'Sistemas',
            'Recursos Humanos',
            'Ventas',
            'Vendedor',
            'Inventario',
        ];

        foreach ($roleNames as $roleName) {
            DB::table('roles')->updateOrInsert(
                ['name' => $roleName],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $roles = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->get()
            ->keyBy('name');

        $permissionIdsByName = DB::table('permissions')
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id);

        $allPermissionNames = $permissionIdsByName->keys()->all();

        $this->syncRolePermissions($roles['Administrador']->id, $allPermissionNames, $permissionIdsByName);
        $this->syncRolePermissions($roles['Super Administrador']->id, $allPermissionNames, $permissionIdsByName);

        $this->syncRolePermissions(
            $roles['Sistemas']->id,
            $this->permissionsStartingWith($permissionIdsByName, ['users.', 'systems.']),
            $permissionIdsByName
        );

        $this->syncRolePermissions(
            $roles['Recursos Humanos']->id,
            [
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
                'attendance.view',
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
                'files.export',
            ],
            $permissionIdsByName
        );

        $salesPermissions = [
            'sales.view',
            'sales.create',
            'sales.purchase-lists.view',
            'sales.purchase-lists.create',
            'sales.purchase-lists.update',
            'sales.purchase-lists.delete',
            'sales.purchase-orders.view',
            'sales.purchase-orders.receive',
            'systems.qz.sign',
        ];

        $this->syncRolePermissions($roles['Ventas']->id, $salesPermissions, $permissionIdsByName);
        $this->syncRolePermissions($roles['Vendedor']->id, $salesPermissions, $permissionIdsByName);

        $this->syncRolePermissions(
            $roles['Inventario']->id,
            [
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
                'reports.audits.view',
                'reports.audits.export.excel',
                'reports.audits.export.pdf',
                'reports.inventory.view',
                'reports.inventory.export.excel',
                'reports.inventory.export.pdf',
                'reports.movements.view',
                'reports.movements.export.excel',
                'reports.movements.export.pdf',
            ],
            $permissionIdsByName
        );

        $adminUser = User::where('email', 'carlos@oxilive.com.mx')->first();

        if ($adminUser) {
            $adminUser->forceFill(['role_id' => $roles['Administrador']->id])->save();
        }
    }

    private function syncRolePermissions(int $roleId, array $permissionNames, $permissionIdsByName): void
    {
        $permissionIds = collect($permissionNames)
            ->unique()
            ->map(fn (string $permissionName) => $permissionIdsByName[$permissionName] ?? null)
            ->filter()
            ->values()
            ->all();

        DB::table('role_permission')
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    private function permissionsStartingWith($permissionIdsByName, array $prefixes): array
    {
        return $permissionIdsByName
            ->keys()
            ->filter(fn (string $permissionName) => collect($prefixes)->contains(
                fn (string $prefix) => str_starts_with($permissionName, $prefix)
            ))
            ->values()
            ->all();
    }
}
