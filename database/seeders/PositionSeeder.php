<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'Administrador general', 'department' => 'Administración', 'description' => 'Administra operación, permisos y seguimiento general.'],
            ['name' => 'Supervisor de inventario', 'department' => 'Inventario', 'description' => 'Supervisa stock, lotes, auditorías y órdenes generales.'],
            ['name' => 'Jefe de almacén', 'department' => 'Inventario', 'description' => 'Coordina entradas, salidas y movimientos entre sucursales.'],
            ['name' => 'Analista de reposición', 'department' => 'Inventario', 'description' => 'Analiza ventas históricas y necesidades de compra.'],
            ['name' => 'Auxiliar RH', 'department' => 'Recursos Humanos', 'description' => 'Apoya incidencias, horarios y expediente laboral.'],
            ['name' => 'Capturista RH', 'department' => 'Recursos Humanos', 'description' => 'Registra asistencia, incidencias y datos del personal.'],
            ['name' => 'Desarrollador', 'department' => 'Sistemas', 'description' => 'Mantiene soluciones internas y soporte de usuarios.'],
            ['name' => 'Analista de sistemas', 'department' => 'Sistemas', 'description' => 'Analiza información, configuraciones e incidencias técnicas.'],
            ['name' => 'Soporte TI', 'department' => 'Sistemas', 'description' => 'Atiende usuarios, impresoras, tickets y etiquetas.'],
            ['name' => 'Vendedora', 'department' => 'Ventas', 'description' => 'Atiende mostrador, ventas, cortes y solicitudes de compra.'],
            ['name' => 'Encargada de sucursal', 'department' => 'Ventas', 'description' => 'Coordina operación comercial y seguimiento de órdenes.'],
            ['name' => 'Ejecutiva comercial', 'department' => 'Ventas', 'description' => 'Da seguimiento a clientes y objetivos comerciales.'],
            ['name' => 'Chofer repartidor', 'department' => 'Logística', 'description' => 'Entrega mercancía y registra incidencias de traslado.'],
        ];

        foreach ($positions as $position) {
            $departmentId = DB::table('departments')
                ->where('name', $position['department'])
                ->value('id');

            if (! $departmentId) {
                continue;
            }

            DB::table('positions')->updateOrInsert(
                ['name' => $position['name'], 'department_id' => $departmentId],
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
