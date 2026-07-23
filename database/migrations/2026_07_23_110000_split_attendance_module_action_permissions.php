<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $replacements = [
        'attendance.schedules.manage' => [
            'attendance.schedules.create',
            'attendance.schedules.update',
            'attendance.schedules.delete',
        ],
        'attendance.schedule-assignments.manage' => [
            'attendance.schedule-assignments.create',
            'attendance.schedule-assignments.update',
            'attendance.schedule-assignments.delete',
        ],
        'attendance.incidents.create' => [
            'attendance.incidents.create',
            'attendance.incidents.update',
            'attendance.incidents.delete',
        ],
        'attendance.incidents.review' => [
            'attendance.incidents.approve',
            'attendance.incidents.reject',
        ],
    ];

    public function up(): void
    {
        $now = now();

        foreach (collect($this->replacements)->flatten()->unique() as $name) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        foreach ($this->replacements as $legacyName => $newNames) {
            $legacyId = DB::table('permissions')->where('name', $legacyName)->value('id');
            if (! $legacyId) continue;

            $roleIds = DB::table('role_permission')->where('permission_id', $legacyId)->pluck('role_id');
            $userIds = DB::table('permission_user')->where('permission_id', $legacyId)->pluck('user_id');

            foreach ($newNames as $newName) {
                $permissionId = DB::table('permissions')->where('name', $newName)->value('id');

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

            if (! in_array($legacyName, $newNames, true)) {
                DB::table('role_permission')->where('permission_id', $legacyId)->delete();
                DB::table('permission_user')->where('permission_id', $legacyId)->delete();
                DB::table('permissions')->where('id', $legacyId)->delete();
            }
        }
    }

    public function down(): void
    {
        $now = now();

        foreach ($this->replacements as $legacyName => $newNames) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $legacyName],
                ['created_at' => $now, 'updated_at' => $now],
            );

            $legacyId = DB::table('permissions')->where('name', $legacyName)->value('id');
            $newIds = DB::table('permissions')->whereIn('name', $newNames)->pluck('id');

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
        }
    }
};
