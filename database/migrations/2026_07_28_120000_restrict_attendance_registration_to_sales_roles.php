<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['name' => 'Vendedor'],
            ['created_at' => now(), 'updated_at' => now()],
        );

        $permissionIds = DB::table('permissions')
            ->whereIn('name', ['attendance.register', 'attendance.corrections.request'])
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        $authorizedRoleIds = DB::table('roles')
            ->whereIn('name', ['Ventas', 'Vendedor'])
            ->pluck('id');

        DB::table('role_permission')
            ->whereIn('permission_id', $permissionIds)
            ->whereNotIn('role_id', $authorizedRoleIds)
            ->delete();

        foreach ($authorizedRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        // No se restauran permisos de registro para roles no vendedores
        // para evitar reabrir asistencia a usuarios administrativos.
    }
};
