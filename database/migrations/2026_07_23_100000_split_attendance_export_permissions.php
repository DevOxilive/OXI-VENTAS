<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $legacyId = DB::table('permissions')->where('name', 'attendance.reports')->value('id');

        foreach (['attendance.export.excel', 'attendance.export.pdf'] as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $newIds = DB::table('permissions')
            ->whereIn('name', ['attendance.export.excel', 'attendance.export.pdf'])
            ->pluck('id');

        if ($legacyId) {
            $roleIds = DB::table('role_permission')
                ->where('permission_id', $legacyId)
                ->pluck('role_id');
            $userIds = DB::table('permission_user')
                ->where('permission_id', $legacyId)
                ->pluck('user_id');

            foreach ($newIds as $permissionId) {
                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }

                foreach ($userIds as $userId) {
                    DB::table('permission_user')->updateOrInsert([
                        'user_id' => $userId,
                        'permission_id' => $permissionId,
                    ]);
                }
            }

            DB::table('role_permission')->where('permission_id', $legacyId)->delete();
            DB::table('permission_user')->where('permission_id', $legacyId)->delete();
            DB::table('permissions')->where('id', $legacyId)->delete();
        }
    }

    public function down(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'attendance.reports'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $legacyId = DB::table('permissions')->where('name', 'attendance.reports')->value('id');
        $newIds = DB::table('permissions')
            ->whereIn('name', ['attendance.export.excel', 'attendance.export.pdf'])
            ->pluck('id');

        foreach (DB::table('role_permission')->whereIn('permission_id', $newIds)->pluck('role_id')->unique() as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $legacyId,
            ]);
        }

        foreach (DB::table('permission_user')->whereIn('permission_id', $newIds)->pluck('user_id')->unique() as $userId) {
            DB::table('permission_user')->updateOrInsert([
                'user_id' => $userId,
                'permission_id' => $legacyId,
            ]);
        }

        DB::table('role_permission')->whereIn('permission_id', $newIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }
};
