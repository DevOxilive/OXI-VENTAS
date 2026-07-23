<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'attendance.schedule-assignments.view',
            'attendance.schedule-assignments.manage',
        ] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'attendance.schedules.view',
                'attendance.schedules.manage',
                'attendance.schedule-assignments.view',
                'attendance.schedule-assignments.manage',
            ])
            ->pluck('id', 'name');

        $viewRoleIds = DB::table('role_permission')
            ->whereIn('permission_id', array_filter([
                $permissionIds['attendance.schedules.view'] ?? null,
                $permissionIds['attendance.schedules.manage'] ?? null,
            ]))
            ->pluck('role_id')
            ->unique();

        foreach ($viewRoleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionIds['attendance.schedule-assignments.view'],
            ]);
        }

        $manageRoleIds = DB::table('role_permission')
            ->where('permission_id', $permissionIds['attendance.schedules.manage'] ?? 0)
            ->pluck('role_id')
            ->unique();

        foreach ($manageRoleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionIds['attendance.schedule-assignments.manage'],
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'attendance.schedule-assignments.view',
                'attendance.schedule-assignments.manage',
            ])
            ->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
