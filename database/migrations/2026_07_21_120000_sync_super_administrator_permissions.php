<?php

use App\Support\SystemPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $administratorRoleId = DB::table('roles')->where('name', 'Administrador')->value('id');
        $superAdministratorRoleId = DB::table('roles')->where('name', 'Super Administrador')->value('id');

        if (!$administratorRoleId || !$superAdministratorRoleId) {
            return;
        }

        $permissionIds = DB::table('role_permission')
            ->where('role_id', $administratorRoleId)
            ->pluck('permission_id')
            ->merge(
                DB::table('permissions')
                    ->whereIn('name', SystemPermission::exclusive())
                    ->pluck('id')
            )
            ->unique()
            ->values();

        DB::table('role_permission')->where('role_id', $superAdministratorRoleId)->delete();

        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $superAdministratorRoleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // Permission assignments are data, so rolling back must not remove access.
    }
};
