<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'sales.cash-closures.reports'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'sales.cash-closures.reports')
            ->value('id');

        $roleIds = DB::table('roles')
            ->whereIn('name', ['Super Administrador', 'Administrador'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'sales.cash-closures.reports')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('role_permission')->where('permission_id', $permissionId)->delete();
        DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
