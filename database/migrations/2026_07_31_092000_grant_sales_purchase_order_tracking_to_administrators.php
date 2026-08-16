<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'sales.purchase-orders.view',
        'sales.purchase-orders.receive',
    ];

    private const ROLES = [
        'Administrador',
        'Super Administrador',
    ];

    public function up(): void
    {
        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

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
        $roleIds = DB::table('roles')->whereIn('name', self::ROLES)->pluck('id');
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        DB::table('role_permission')
            ->whereIn('role_id', $roleIds)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
