<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'reports.cash-closures.export.excel',
        'reports.cash-closures.export.pdf',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $sourceId = DB::table('permissions')->where('name', 'reports.cash-closures.view')->value('id');

        if (! $sourceId) {
            return;
        }

        $roleIds = DB::table('role_permission')->where('permission_id', $sourceId)->pluck('role_id');
        $userAssignments = DB::table('permission_user')->where('permission_id', $sourceId)->get();

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permissions')->where('name', $permission)->value('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }

            foreach ($userAssignments as $assignment) {
                DB::table('permission_user')->updateOrInsert(
                    ['user_id' => $assignment->user_id, 'permission_id' => $permissionId],
                    ['mode' => $assignment->mode, 'created_at' => $assignment->created_at, 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
