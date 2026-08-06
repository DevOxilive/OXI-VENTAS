<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'reports.sales.view',
        'reports.sales.export.excel',
        'reports.sales.export.pdf',
    ];

    private const PERMISSION_MAP = [
        'sales.reports' => [
            'reports.sales.view',
            'reports.sales.export.excel',
            'reports.sales.export.pdf',
        ],
        'reports.inventory.view' => [
            'reports.sales.view',
        ],
        'reports.inventory.export.excel' => [
            'reports.sales.export.excel',
        ],
        'reports.inventory.export.pdf' => [
            'reports.sales.export.pdf',
        ],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        foreach (self::PERMISSION_MAP as $sourcePermission => $targetPermissions) {
            $sourceId = DB::table('permissions')->where('name', $sourcePermission)->value('id');

            if (! $sourceId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $sourceId)
                ->pluck('role_id');
            $userAssignments = DB::table('permission_user')
                ->where('permission_id', $sourceId)
                ->get();

            foreach ($targetPermissions as $targetPermission) {
                $targetId = DB::table('permissions')->where('name', $targetPermission)->value('id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $targetId,
                    ]);
                }

                foreach ($userAssignments as $assignment) {
                    DB::table('permission_user')->updateOrInsert(
                        [
                            'user_id' => $assignment->user_id,
                            'permission_id' => $targetId,
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
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', self::PERMISSIONS)
            ->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
