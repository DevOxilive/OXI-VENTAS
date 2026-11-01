<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $roles = DB::table('roles')
                ->whereIn('name', ['Ventas', 'Vendedor'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $salesRole = $roles->firstWhere('name', 'Ventas');
            $legacyRoles = $roles->where('name', 'Vendedor');

            if (! $salesRole && $legacyRoles->isEmpty()) {
                return;
            }

            if (! $salesRole) {
                $salesRole = $legacyRoles->first();
                DB::table('roles')->where('id', $salesRole->id)->update([
                    'name' => 'Ventas',
                    'updated_at' => now(),
                ]);
            }

            $salesRoleId = (int) $salesRole->id;
            $legacyRoleIds = $legacyRoles
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $salesRoleId)
                ->values()
                ->all();

            $now = now();
            $legacyPermissionIds = DB::table('role_permission')
                ->whereIn('role_id', $legacyRoleIds)
                ->distinct()
                ->pluck('permission_id');

            // RoleSeeder define estas dos capacidades como parte del rol de Ventas.
            // Una base ya existente no vuelve a ejecutar seeders durante el despliegue,
            // por eso se garantizan aquí al unificar ambos roles.
            $requiredPermissionNames = [
                'attendance.register',
                'attendance.corrections.request',
            ];
            $requiredPermissionIds = DB::table('permissions')
                ->whereIn('name', $requiredPermissionNames)
                ->pluck('id', 'name');

            if ($requiredPermissionIds->count() !== count($requiredPermissionNames)) {
                throw new RuntimeException('Faltan permisos de asistencia requeridos para consolidar el rol Ventas.');
            }

            $permissionRows = $legacyPermissionIds
                ->merge($requiredPermissionIds->values())
                ->unique()
                ->values()
                ->map(fn ($permissionId) => [
                    'role_id' => $salesRoleId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

            if ($permissionRows) {
                DB::table('role_permission')->insertOrIgnore($permissionRows);
            }

            // Incluye usuarios eliminados temporalmente; sus excepciones individuales se conservan.
            if ($legacyRoleIds !== []) {
                // La consulta sin modelo incluye también cuentas eliminadas temporalmente.
                DB::table('users')->whereIn('role_id', $legacyRoleIds)->update([
                    'role_id' => $salesRoleId,
                ]);

                DB::table('role_permission')->whereIn('role_id', $legacyRoleIds)->delete();
                DB::table('roles')->whereIn('id', $legacyRoleIds)->delete();
            }
        });
    }

    public function down(): void
    {
        // La unificación se conserva: no es posible inferir el rol original de cada usuario.
    }
};
