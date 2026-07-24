<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['first_name' => 'Kevin', 'email' => 'kevin@oxilive.com.mx', 'position' => 'Desarrollador', 'department' => 'Sistemas'],
            ['first_name' => 'Brayan', 'email' => 'brayan@oxilive.com.mx', 'position' => 'Analista', 'department' => 'Sistemas'],
            ['first_name' => 'Asael', 'email' => 'asael@oxilive.com.mx', 'position' => 'Soporte TI', 'department' => 'Sistemas'],
            ['first_name' => 'Ana Lilia', 'email' => 'ana.lilia@oxilive.com.mx', 'position' => 'Supervisor', 'department' => 'Inventario'],
            ['first_name' => 'Laura', 'email' => 'laura@oxilive.com.mx', 'position' => 'Jefe de almacén', 'department' => 'Inventario'],
            ['first_name' => 'Blanca', 'email' => 'blanca@oxilive.com.mx', 'position' => 'Supervisor', 'department' => 'Inventario'],
            ['first_name' => 'Diana', 'email' => 'diana@oxilive.com.mx', 'position' => 'Jefe de almacén', 'department' => 'Inventario'],
            ['first_name' => 'Rodrigo', 'email' => 'rodrigo@oxilive.com.mx', 'position' => 'Supervisor', 'department' => 'Inventario'],
            ['first_name' => 'Toño', 'email' => 'tono@oxilive.com.mx', 'position' => 'Jefe de almacén', 'department' => 'Inventario'],
            ['first_name' => 'Margarita', 'email' => 'margarita@oxilive.com.mx', 'position' => 'Encargada de sucursal', 'department' => 'Ventas'],
            ['first_name' => 'Mairani', 'email' => 'mairani@oxilive.com.mx', 'position' => 'Vendedora', 'department' => 'Ventas'],
            ['first_name' => 'Patria', 'email' => 'patria@oxilive.com.mx', 'position' => 'Auxiliar RH', 'department' => 'Recursos Humanos'],
            ['first_name' => 'Carlos', 'email' => 'carlos@oxilive.com.mx', 'position' => 'Desarrollador', 'department' => 'Sistemas'],
        ];

        foreach ($employees as $employee) {
            $position = Position::query()
                ->where('name', $employee['position'])
                ->whereHas('department', fn ($query) => $query->where('name', $employee['department']))
                ->firstOrFail();

            Employee::updateOrCreate(
                ['email' => $employee['email']],
                [
                    'first_name' => $employee['first_name'],
                    'last_name' => '',
                    'employment_status' => 'Activo',
                    'position_id' => $position->id,
                ],
            );
        }
    }
}
