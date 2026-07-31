<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_MAP = [
        'inventory.purchase-reports.view' => ['sales.purchase-lists.view'],
        'inventory.purchase-reports.create' => ['sales.purchase-lists.create'],
        'inventory.purchase-reports.update' => ['sales.purchase-lists.update'],
        'inventory.purchase-reports.delete' => ['sales.purchase-lists.delete'],
        'inventory.purchase-orders.generate.view' => ['inventory.purchase-orders.source.view'],
        'inventory.purchase-orders.generate.create' => ['inventory.purchase-orders.general.create'],
        'inventory.purchase-orders.generate.update' => [
            'inventory.purchase-orders.source.update',
            'inventory.purchase-orders.source.review',
        ],
        'inventory.purchase-orders.generate.transfer' => ['inventory.purchase-orders.source.transfer'],
        'inventory.purchase-orders.purchasing.view' => ['inventory.purchase-orders.general.view'],
        'inventory.purchase-orders.completed.view' => ['inventory.purchase-orders.general.view'],
        'inventory.purchase-orders.costs' => [
            'inventory.purchase-orders.general.update',
            'inventory.purchase-orders.general.complete',
        ],
    ];

    private const SALES_ORDER_PERMISSIONS = [
        'sales.purchase-orders.view',
        'sales.purchase-orders.receive',
    ];

    public function up(): void
    {
        $newPermissions = collect(self::PERMISSION_MAP)
            ->flatten()
            ->merge(self::SALES_ORDER_PERMISSIONS)
            ->unique()
            ->values();

        foreach ($newPermissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach (self::PERMISSION_MAP as $oldName => $newNames) {
            $oldPermissionId = DB::table('permissions')->where('name', $oldName)->value('id');

            if (! $oldPermissionId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $oldPermissionId)
                ->pluck('role_id');
            $userIds = DB::table('permission_user')
                ->where('permission_id', $oldPermissionId)
                ->pluck('user_id');

            foreach ($newNames as $newName) {
                $newPermissionId = DB::table('permissions')->where('name', $newName)->value('id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $newPermissionId,
                    ]);
                }

                foreach ($userIds as $userId) {
                    DB::table('permission_user')->updateOrInsert([
                        'user_id' => $userId,
                        'permission_id' => $newPermissionId,
                    ]);
                }
            }
        }

        $salesRoleIds = DB::table('roles')
            ->whereIn('name', ['Ventas', 'Vendedor'])
            ->pluck('id');
        $salesOrderPermissionIds = DB::table('permissions')
            ->whereIn('name', self::SALES_ORDER_PERMISSIONS)
            ->pluck('id');

        foreach ($salesRoleIds as $roleId) {
            foreach ($salesOrderPermissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $inventoryRoleIds = DB::table('roles')->where('name', 'Inventario')->pluck('id');
        $inventoryPermissionIds = DB::table('permissions')
            ->where('name', 'like', 'inventory.purchase-orders.%')
            ->pluck('id');

        foreach ($inventoryRoleIds as $roleId) {
            foreach ($inventoryPermissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $salesPermissionIds = DB::table('permissions')
            ->where(function ($query) {
                $query
                    ->where('name', 'like', 'sales.purchase-lists.%')
                    ->orWhere('name', 'like', 'sales.purchase-orders.%');
            })
            ->pluck('id');

        DB::table('role_permission')
            ->whereIn('role_id', $inventoryRoleIds)
            ->whereIn('permission_id', $salesPermissionIds)
            ->delete();

        $oldPermissionIds = DB::table('permissions')
            ->whereIn('name', array_keys(self::PERMISSION_MAP))
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $oldPermissionIds)->delete();
        DB::table('role_permission')->whereIn('permission_id', $oldPermissionIds)->delete();
        DB::table('permissions')->whereIn('id', $oldPermissionIds)->delete();
    }

    public function down(): void
    {
        foreach (array_keys(self::PERMISSION_MAP) as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
