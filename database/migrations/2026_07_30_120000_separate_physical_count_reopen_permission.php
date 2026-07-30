<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'audits.physical-counts.reopen'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $closePermissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.close')
            ->value('id');
        $reopenPermissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.reopen')
            ->value('id');

        if (! $closePermissionId || ! $reopenPermissionId) {
            return;
        }

        foreach (DB::table('role_permission')->where('permission_id', $closePermissionId)->pluck('role_id') as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $reopenPermissionId,
            ]);
        }

        foreach (DB::table('permission_user')->where('permission_id', $closePermissionId)->get() as $assignment) {
            DB::table('permission_user')->updateOrInsert(
                [
                    'user_id' => $assignment->user_id,
                    'permission_id' => $reopenPermissionId,
                ],
                [
                    'mode' => $assignment->mode,
                    'created_at' => $assignment->created_at ?? $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $reopenPermissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.reopen')
            ->value('id');

        if (! $reopenPermissionId) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $reopenPermissionId)->delete();
        DB::table('permission_user')->where('permission_id', $reopenPermissionId)->delete();
        DB::table('permissions')->where('id', $reopenPermissionId)->delete();
    }
};
