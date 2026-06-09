<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 🔹 Crear roles
        $roles = [
            'Administrador',
            'Sistemas',
            'Recursos Humanos',
            'Ventas',
            'Inventario',
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                [
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
        }

        // 🔹 Obtener roles
        $adminRole = DB::table('roles')->where('name', 'Administrador')->first();
        $sistemasRole = DB::table('roles')->where('name', 'Sistemas')->first();
        $rhRole = DB::table('roles')->where('name', 'Recursos Humanos')->first();
        $ventasRole = DB::table('roles')->where('name', 'Ventas')->first();
        $inventarioRole = DB::table('roles')->where('name', 'Inventario')->first();

        // 🔹 Obtener permisos
        $permissions = DB::table('permissions')->get();

        // 🔹 ADMIN → todos los permisos
        foreach ($permissions as $permission) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id
            ]);
        }

        // 🔹 SISTEMAS → todo lo técnico (usuarios + roles)
// SISTEMAS → users + roles
        foreach ($permissions as $permission) {
            if (
                str_starts_with($permission->name, 'users.') ||
                str_starts_with($permission->name, 'roles.')
            ) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $sistemasRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }

        // 🔹 RH → empleados
        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'empleados.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $rhRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }

        // 🔹 VENTAS → (cuando agregues permisos ventas.*)
        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'ventas.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $ventasRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }

        // 🔹 INVENTARIO → (cuando agregues permisos inventario.*)
        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'inventario.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $inventarioRole->id,
                    'permission_id' => $permission->id
                ]);
            }
        }

        // 🔹 Asignar rol Administrador al usuario admin
        $adminUser = User::where('email', 'admin@oxilive.com.mx')->first();

        if ($adminUser && $adminRole) {
            $adminUser->role_id = $adminRole->id;
            $adminUser->save();
        }
    }
}
