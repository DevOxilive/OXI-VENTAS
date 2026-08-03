<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UNUSED_PERMISSIONS = [
        'system.audit.filter-advanced',
        'system.settings.manage',
        'system.integrations.manage',
        'system.tools.access',
        'system.monitoring.view',
        'system.statistics.view',
        'system.logs.view',
        'system.maintenance.manage',
        'system.records.view-all',
    ];

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'systems.qz.sign'],
            ['created_at' => now(), 'updated_at' => now()]
        );

        $qzPermissionId = DB::table('permissions')->where('name', 'systems.qz.sign')->value('id');
        $roleIds = DB::table('role_permission')
            ->join('permissions', 'permissions.id', '=', 'role_permission.permission_id')
            ->whereIn('permissions.name', ['sales.create', 'systems.labels.print'])
            ->pluck('role_permission.role_id')
            ->unique();

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $qzPermissionId,
            ]);
        }

        $unusedIds = DB::table('permissions')->whereIn('name', self::UNUSED_PERMISSIONS)->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $unusedIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $unusedIds)->delete();
        DB::table('permissions')->whereIn('id', $unusedIds)->delete();
    }

    public function down(): void
    {
        $qzPermissionId = DB::table('permissions')->where('name', 'systems.qz.sign')->value('id');

        if ($qzPermissionId) {
            DB::table('role_permission')->where('permission_id', $qzPermissionId)->delete();
            DB::table('permission_user')->where('permission_id', $qzPermissionId)->delete();
            DB::table('permissions')->where('id', $qzPermissionId)->delete();
        }

        foreach (self::UNUSED_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
};
