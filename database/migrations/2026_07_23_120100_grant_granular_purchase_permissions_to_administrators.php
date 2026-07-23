<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'inventory.purchase-orders.generate.view',
        'inventory.purchase-orders.generate.create',
        'inventory.purchase-orders.generate.update',
        'inventory.purchase-orders.generate.transfer',
        'inventory.purchase-orders.purchasing.view',
        'inventory.purchase-orders.completed.view',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrador', 'Super Administrador'])
            ->pluck('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', ['Administrador', 'Super Administrador'])
            ->pluck('id');
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table('role_permission')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
