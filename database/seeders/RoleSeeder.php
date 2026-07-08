<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
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
                    'updated_at' => now(),
                ]
            );
        }

        $adminRole = DB::table('roles')->where('name', 'Administrador')->first();
        $systemsRole = DB::table('roles')->where('name', 'Sistemas')->first();
        $humanResourcesRole = DB::table('roles')->where('name', 'Recursos Humanos')->first();
        $salesRole = DB::table('roles')->where('name', 'Ventas')->first();
        $inventoryRole = DB::table('roles')->where('name', 'Inventario')->first();

        $permissions = DB::table('permissions')->get();

        foreach ($permissions as $permission) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        foreach ($permissions as $permission) {
            if (
                str_starts_with($permission->name, 'users.')
                || str_starts_with($permission->name, 'systems.tickets.')
            ) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $systemsRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'employees.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $humanResourcesRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'sales.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $salesRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        foreach ($permissions as $permission) {
            if (str_starts_with($permission->name, 'inventory.')) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $inventoryRole->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }

        $adminUser = User::where('email', 'admin@oxilive.com.mx')->first();

        if ($adminUser && $adminRole) {
            $adminUser->role_id = $adminRole->id;
            $adminUser->save();
        }
    }
}
