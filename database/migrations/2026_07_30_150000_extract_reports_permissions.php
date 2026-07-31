<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_MAP = [
        'audits.physical-counts.reports' => ['reports.audits.view'],
        'sales.cash-closures.reports' => ['reports.cash-closures.view'],
        'inventory.view' => ['reports.inventory.view', 'reports.movements.view'],
        'inventory.create' => ['reports.inventory.view', 'reports.movements.view'],
        'inventory.update' => ['reports.inventory.view', 'reports.movements.view'],
        'inventory.delete' => ['reports.inventory.view', 'reports.movements.view'],
    ];

    private const OBSOLETE_PERMISSIONS = [
        'audits.physical-counts.reports',
        'sales.cash-closures.reports',
        'inventory.view',
        'inventory.create',
        'inventory.update',
        'inventory.delete',
    ];

    public function up(): void
    {
        foreach (array_unique(array_merge(...array_values(self::PERMISSION_MAP))) as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach (self::PERMISSION_MAP as $legacyPermission => $newPermissions) {
            $legacyId = DB::table('permissions')->where('name', $legacyPermission)->value('id');

            if (! $legacyId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $legacyId)
                ->pluck('role_id');
            $userAssignments = DB::table('permission_user')
                ->where('permission_id', $legacyId)
                ->get();

            foreach ($newPermissions as $newPermission) {
                $newId = DB::table('permissions')->where('name', $newPermission)->value('id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $newId,
                    ]);
                }

                foreach ($userAssignments as $assignment) {
                    DB::table('permission_user')->updateOrInsert(
                        [
                            'user_id' => $assignment->user_id,
                            'permission_id' => $newId,
                        ],
                        [
                            'mode' => $assignment->mode,
                            'created_at' => $assignment->created_at,
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }

        $obsoleteIds = DB::table('permissions')
            ->whereIn('name', self::OBSOLETE_PERMISSIONS)
            ->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $obsoleteIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $obsoleteIds)->delete();
        DB::table('permissions')->whereIn('id', $obsoleteIds)->delete();
    }

    public function down(): void
    {
        foreach (self::OBSOLETE_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach (self::PERMISSION_MAP as $legacyPermission => $newPermissions) {
            $legacyId = DB::table('permissions')->where('name', $legacyPermission)->value('id');
            $newIds = DB::table('permissions')->whereIn('name', $newPermissions)->pluck('id');

            foreach (DB::table('role_permission')->whereIn('permission_id', $newIds)->pluck('role_id')->unique() as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $legacyId,
                ]);
            }

            foreach (DB::table('permission_user')->whereIn('permission_id', $newIds)->get() as $assignment) {
                DB::table('permission_user')->updateOrInsert(
                    [
                        'user_id' => $assignment->user_id,
                        'permission_id' => $legacyId,
                    ],
                    [
                        'mode' => $assignment->mode,
                        'created_at' => $assignment->created_at,
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $newIds = DB::table('permissions')
            ->whereIn('name', array_unique(array_merge(...array_values(self::PERMISSION_MAP))))
            ->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $newIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }
};
