<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $pricingName = 'inventory.products.manage-pricing';
        $expectedName = 'sales.cash-closures.view-expected';

        foreach ([$pricingName, $expectedName] as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $pricingId = DB::table('permissions')->where('name', $pricingName)->value('id');
        $expectedId = DB::table('permissions')->where('name', $expectedName)->value('id');
        $reportViewId = DB::table('permissions')->where('name', 'reports.cash-closures.view')->value('id');

        $pricingRoles = DB::table('roles')
            ->whereIn('name', ['Sistemas', 'Administrador', 'Super Administrador', 'Inventario'])
            ->pluck('id');

        foreach ($pricingRoles as $roleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $pricingId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($reportViewId) {
            foreach (DB::table('role_permission')->where('permission_id', $reportViewId)->pluck('role_id') as $roleId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $expectedId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach (DB::table('permission_user')->where('permission_id', $reportViewId)->get(['user_id', 'mode']) as $assignment) {
                DB::table('permission_user')->insertOrIgnore([
                    'user_id' => $assignment->user_id,
                    'permission_id' => $expectedId,
                    'mode' => $assignment->mode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')
            ->whereIn('name', [
                'inventory.products.manage-pricing',
                'sales.cash-closures.view-expected',
            ])
            ->pluck('id');

        DB::table('permission_user')->whereIn('permission_id', $ids)->delete();
        DB::table('role_permission')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};
