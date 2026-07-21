<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SystemPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Administrador',
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

        $superAdministratorRole = DB::table('roles')->where('name', 'Super Administrador')->first();
        $adminRole = DB::table('roles')->where('name', 'Administrador')->first();
        $systemsRole = DB::table('roles')->where('name', 'Sistemas')->first();
        $humanResourcesRole = DB::table('roles')->where('name', 'Recursos Humanos')->first();
        $salesRole = DB::table('roles')->where('name', 'Ventas')->first();
        $inventoryRole = DB::table('roles')->where('name', 'Inventario')->first();

        $permissions = DB::table('permissions')->get();

        foreach ($permissions as $permission) {
            if (in_array($permission->name, SystemPermission::exclusive(), true)) {
                continue;
            }

            DB::table('role_permission')->updateOrInsert([
                'role_id' => $adminRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        // Super Administrador is Administrador plus the exclusive system controls.
        // Rebuilding this role from those two sources prevents permissions drifting.
        $superAdministratorPermissionIds = DB::table('role_permission')
            ->where('role_id', $adminRole->id)
            ->pluck('permission_id')
            ->merge(
                DB::table('permissions')
                    ->whereIn('name', SystemPermission::exclusive())
                    ->pluck('id')
            )
            ->unique()
            ->values();

        DB::table('role_permission')
            ->where('role_id', $superAdministratorRole->id)
            ->delete();

        foreach ($superAdministratorPermissionIds as $permissionId) {
            DB::table('role_permission')->insert([
                'role_id' => $superAdministratorRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        foreach ($permissions as $permission) {
            if (
                str_starts_with($permission->name, 'users.')
                || str_starts_with($permission->name, 'systems.tickets.')
                || str_starts_with($permission->name, 'systems.cash-closure-tickets.')
                || str_starts_with($permission->name, 'systems.labels.')
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
            if (
                str_starts_with($permission->name, 'inventory.')
                || str_starts_with($permission->name, 'audits.physical-counts.')
                || $permission->name === 'files.export'
            ) {
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
