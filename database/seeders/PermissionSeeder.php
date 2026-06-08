<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            /*
            |--------------------------------------------------------------------------
            | EMPLOYEES (CAPITAL HUMANO)
            |--------------------------------------------------------------------------
            */

            'empleados.ver',
            'empleados.crear',
            'empleados.editar',
            'empleados.eliminar',

            /*
            |--------------------------------------------------------------------------
            | FILE EXPORTS
            |--------------------------------------------------------------------------
            */

            'exportar.archivos',

            /*
            |--------------------------------------------------------------------------
            | ROLES
            |--------------------------------------------------------------------------
            */

            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',

            /*
            |--------------------------------------------------------------------------
            | USERS (SYSTEMS)
            |--------------------------------------------------------------------------
            */

            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            /*
            |--------------------------------------------------------------------------
            | INVENTORY
            |--------------------------------------------------------------------------
            */

            'inventario.ver',
            'inventario.crear',
            'inventario.editar',
            'inventario.eliminar',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}