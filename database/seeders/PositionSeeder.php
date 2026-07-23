<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'Supervisor', 'department' => 'Inventario', 'description' => 'Supervisa la operación del inventario.'],
            ['name' => 'Jefe de almacén', 'department' => 'Inventario', 'description' => 'Coordina el almacén y sus movimientos.'],
            ['name' => 'Auxiliar RH', 'department' => 'Recursos Humanos', 'description' => 'Apoya los procesos de Capital Humano.'],
            ['name' => 'Capturista', 'department' => 'Recursos Humanos', 'description' => 'Registra y mantiene información administrativa.'],
            ['name' => 'Desarrollador', 'department' => 'Sistemas', 'description' => 'Desarrolla y mantiene soluciones internas.'],
            ['name' => 'Analista', 'department' => 'Sistemas', 'description' => 'Analiza información y procesos del sistema.'],
            ['name' => 'Soporte TI', 'department' => 'Sistemas', 'description' => 'Atiende incidencias tecnológicas.'],
            ['name' => 'Vendedora', 'department' => 'Ventas', 'description' => 'Atiende y registra operaciones de venta.'],
            ['name' => 'Ejecutiva comercial', 'department' => 'Ventas', 'description' => 'Da seguimiento a clientes y objetivos comerciales.'],
            ['name' => 'Asistente de ventas', 'department' => 'Ventas', 'description' => 'Apoya la operación comercial.'],
        ];

        foreach ($positions as $position) {
            $departmentId = DB::table('departments')
                ->where('name', $position['department'])
                ->value('id');

            if (!$departmentId) {
                continue;
            }

            DB::table('positions')->updateOrInsert(
                [
                    'name' => $position['name'],
                    'department_id' => $departmentId,
                ],
                [
                    'description' => $position['description'],
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
