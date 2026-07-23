<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'attendance.view')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        $authorizedRoleIds = DB::table('roles')
            ->whereIn('name', ['Super Administrador', 'Administrador', 'Recursos Humanos'])
            ->pluck('id');

        DB::table('role_permission')
            ->where('permission_id', $permissionId)
            ->whereNotIn('role_id', $authorizedRoleIds)
            ->delete();

        foreach ($authorizedRoleIds as $roleId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        // No se restauran asignaciones para evitar conceder acceso de consulta
        // a roles que pudieron haber sido modificados después de esta migración.
    }
};
