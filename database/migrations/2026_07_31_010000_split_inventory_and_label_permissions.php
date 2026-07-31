<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INVENTORY_PERMISSION_MAP = [
        'inventory.branches.create' => [
            'inventory.branches.stock-in',
        ],
        'inventory.branches.update' => [
            'inventory.branches.stock-out',
            'inventory.branches.stock-adjust',
            'inventory.branches.batches.update',
            'inventory.branches.config.update',
        ],
    ];

    private const NEW_PERMISSIONS = [
        'inventory.branches.stock-in',
        'inventory.branches.stock-out',
        'inventory.branches.stock-adjust',
        'inventory.branches.batches.update',
        'inventory.branches.config.update',
        'systems.labels.print',
    ];

    private const LEGACY_PERMISSIONS = [
        'inventory.branches.create',
        'inventory.branches.update',
        'inventory.branches.delete',
    ];

    public function up(): void
    {
        foreach (self::NEW_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach (self::INVENTORY_PERMISSION_MAP as $legacy => $replacements) {
            $legacyId = DB::table('permissions')->where('name', $legacy)->value('id');

            if (! $legacyId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $legacyId)
                ->pluck('role_id');
            $userIds = DB::table('permission_user')
                ->where('permission_id', $legacyId)
                ->pluck('user_id');

            foreach ($replacements as $replacement) {
                $replacementId = DB::table('permissions')
                    ->where('name', $replacement)
                    ->value('id');

                foreach ($roleIds as $roleId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $replacementId,
                    ]);
                }

                foreach ($userIds as $userId) {
                    DB::table('permission_user')->updateOrInsert([
                        'user_id' => $userId,
                        'permission_id' => $replacementId,
                    ]);
                }
            }
        }

        $labelViewId = DB::table('permissions')
            ->where('name', 'systems.labels.view')
            ->value('id');
        $labelPrintId = DB::table('permissions')
            ->where('name', 'systems.labels.print')
            ->value('id');

        if ($labelViewId && $labelPrintId) {
            foreach (DB::table('role_permission')->where('permission_id', $labelViewId)->pluck('role_id') as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $labelPrintId,
                ]);
            }

            foreach (DB::table('permission_user')->where('permission_id', $labelViewId)->pluck('user_id') as $userId) {
                DB::table('permission_user')->updateOrInsert([
                    'user_id' => $userId,
                    'permission_id' => $labelPrintId,
                ]);
            }
        }

        $legacyIds = DB::table('permissions')
            ->whereIn('name', self::LEGACY_PERMISSIONS)
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('role_permission')->whereIn('permission_id', $legacyIds)->delete();
        DB::table('permissions')->whereIn('id', $legacyIds)->delete();
    }

    public function down(): void
    {
        foreach (self::LEGACY_PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach (self::INVENTORY_PERMISSION_MAP as $legacy => $replacements) {
            $legacyId = DB::table('permissions')->where('name', $legacy)->value('id');
            $replacementIds = DB::table('permissions')
                ->whereIn('name', $replacements)
                ->pluck('id');

            foreach (DB::table('role_permission')->whereIn('permission_id', $replacementIds)->pluck('role_id')->unique() as $roleId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $legacyId,
                ]);
            }

            foreach (DB::table('permission_user')->whereIn('permission_id', $replacementIds)->pluck('user_id')->unique() as $userId) {
                DB::table('permission_user')->updateOrInsert([
                    'user_id' => $userId,
                    'permission_id' => $legacyId,
                ]);
            }
        }

        $newIds = DB::table('permissions')
            ->whereIn('name', self::NEW_PERMISSIONS)
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $newIds)->delete();
        DB::table('role_permission')->whereIn('permission_id', $newIds)->delete();
        DB::table('permissions')->whereIn('id', $newIds)->delete();
    }
};
