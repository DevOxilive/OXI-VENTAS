<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSION_MAP = [
        'reports.audits.view' => ['reports.audits.export.excel', 'reports.audits.export.pdf'],
        'reports.inventory.view' => ['reports.inventory.export.excel', 'reports.inventory.export.pdf'],
        'reports.movements.view' => ['reports.movements.export.excel', 'reports.movements.export.pdf'],
        'sales.cash-closures.create' => ['reports.cash-closures.create'],
        'sales.cash-closures.update' => ['reports.cash-closures.update'],
        'sales.cash-closures.delete' => ['reports.cash-closures.delete'],
    ];

    public function up(): void
    {
        $newPermissions = array_unique(array_merge(...array_values(self::PERMISSION_MAP)));

        foreach ($newPermissions as $permission) {
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
        $newPermissions = array_unique(array_merge(...array_values(self::PERMISSION_MAP)));
        $newIds = DB::table('permissions')->whereIn('name', $newPermissions)->pluck('id');

        DB::table('role_permission')->whereIn('permission_id', $newIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }
};
