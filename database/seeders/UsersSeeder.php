<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // BUSCAR ROL ADMIN
        $adminRole = Role::where('name', 'Administrador')->first();

        // CREAR O ACTUALIZAR ADMIN
        $user = User::updateOrCreate(
            ['email' => 'admin@oxilive.com.mx'],
            [
                'empleado_id' => 1,
                'name' => 'admin',
                'password' => Hash::make('1234567890'),
                'role_id' => $adminRole?->id,
            ]
        );

        // CARGAR TODOS LOS PERMISOS
        $permissions = Permission::pluck('id')->toArray();

        // ASIGNAR TODOS LOS PERMISOS DIRECTOS
        $user->permissions()->sync($permissions);
    }
}
