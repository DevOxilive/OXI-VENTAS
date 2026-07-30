<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $replacements = [
        'audits.physical-counts.update' => [
            'audits.physical-counts.close',
            'audits.physical-counts.reopen',
            'audits.physical-counts.participants',
            'audits.physical-counts.apply',
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

            if (! $legacyId) {
                continue;
            }

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

            DB::table('role_permission')->where('permission_id', $legacyId)->delete();
            DB::table('permission_user')->where('permission_id', $legacyId)->delete();
            DB::table('permissions')->where('id', $legacyId)->delete();
        }

        $viewPermissionId = DB::table('permissions')
            ->where('name', 'audits.physical-counts.view')
            ->value('id');

        if (! $viewPermissionId) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $viewPermissionId)->delete();
        DB::table('permission_user')->where('permission_id', $viewPermissionId)->delete();
        DB::table('permissions')->where('id', $viewPermissionId)->delete();
    }

    public function down(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'audits.physical-counts.update'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $legacyId = DB::table('permissions')->where('name', 'audits.physical-counts.update')->value('id');
        $newIds = DB::table('permissions')
            ->whereIn('name', collect($this->replacements)->flatten())
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
    }
};
