<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_PERMISSIONS = [
        'inventory.purchase-orders.view',
        'inventory.purchase-orders.create',
        'inventory.purchase-orders.update',
        'inventory.purchase-orders.history',
    ];

    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::LEGACY_PERMISSIONS)
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        foreach (self::LEGACY_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
};
