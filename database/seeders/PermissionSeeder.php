<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {

        $permissions = [

            // EMPLEADOS
            'empleados.ver',
            'empleados.crear',
            'empleados.editar',
            'empleados.eliminar',

            // EXPORTAR
            'exportar.archivos',

            // ROLES
            'roles.ver',
            'roles.crear',
            'roles.editar',
            'roles.eliminar',

            // USUARIOS
            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',
            'usuarios.eliminar',

            // INVENTARIO
'inventario.ver',
'inventario.crear',
'inventario.editar',
'inventario.eliminar',
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
