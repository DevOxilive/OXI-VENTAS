<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('email', 'brayan@oxilive.com.mx')
            ->delete();

        Employee::query()
            ->where('email', 'brayan@oxilive.com.mx')
            ->delete();

        $employees = [
            ['first_name' => 'Kevin', 'last_name' => 'Martinez', 'email' => 'kevin@oxilive.com.mx', 'position' => 'Desarrollador'],
            ['first_name' => 'Asael', 'last_name' => 'Sanchez', 'email' => 'asael@oxilive.com.mx', 'position' => 'Soporte TI'],
            ['first_name' => 'Ana', 'last_name' => 'Garcia', 'email' => 'ana.lilia@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Laura', 'last_name' => 'Hernandez', 'email' => 'laura@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Blanca', 'last_name' => 'Ruiz', 'email' => 'blanca@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Diana', 'last_name' => 'Nava', 'email' => 'diana@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Rodrigo', 'last_name' => 'Flores', 'email' => 'rodrigo@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Tono', 'last_name' => 'Vargas', 'email' => 'tono@oxilive.com.mx', 'position' => 'Supervisor de inventario'],
            ['first_name' => 'Margarita', 'last_name' => 'Campos', 'email' => 'margarita@oxilive.com.mx', 'position' => 'Encargada de sucursal'],
            ['first_name' => 'Mairani', 'last_name' => 'Perez', 'email' => 'mairani@oxilive.com.mx', 'position' => 'Vendedora'],
            ['first_name' => 'Patria', 'last_name' => 'Mendoza', 'email' => 'patria@oxilive.com.mx', 'position' => 'Auxiliar RH'],
            ['first_name' => 'Doctor', 'last_name' => 'Carlos', 'email' => 'carlos@oxilive.com.mx', 'position' => 'Administrador general'],
            ['first_name' => 'Adriana', 'last_name' => 'Cuevas', 'email' => 'adriana.cuevas@oxilive.com.mx', 'position' => 'Administrador general'],
        ];

        foreach ($employees as $employee) {
            $position = Position::query()
                ->where('name', $employee['position'])
                ->firstOrFail();

            Employee::updateOrCreate(
                ['email' => $employee['email']],
                [
                    'first_name' => $employee['first_name'],
                    'last_name' => $employee['last_name'],
                    'employment_status' => 'Activo',
                    'start_date' => now()->subMonths(8)->toDateString(),
                    'phone' => '55' . str_pad((string) abs(crc32($employee['email']) % 100000000), 8, '0', STR_PAD_LEFT),
                    'position_id' => $position->id,
                ],
            );
        }
    }
}
