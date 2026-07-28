<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Kevin', 'email' => 'kevin@oxilive.com.mx', 'role' => 'Sistemas'],
            ['name' => 'Brayan', 'email' => 'brayan@oxilive.com.mx', 'role' => 'Sistemas'],
            ['name' => 'Asael', 'email' => 'asael@oxilive.com.mx', 'role' => 'Super Administrador'],
            ['name' => 'Ana Lilia', 'email' => 'ana.lilia@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Laura', 'email' => 'laura@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Blanca', 'email' => 'blanca@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Diana', 'email' => 'diana@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Rodrigo', 'email' => 'rodrigo@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Toño', 'email' => 'tono@oxilive.com.mx', 'role' => 'Inventario'],
            ['name' => 'Margarita', 'email' => 'margarita@oxilive.com.mx', 'role' => 'Ventas'],
            ['name' => 'Mairani', 'email' => 'mairani@oxilive.com.mx', 'role' => 'Ventas'],
            ['name' => 'Patria', 'email' => 'patria@oxilive.com.mx', 'role' => 'Recursos Humanos'],
            ['name' => 'Carlos', 'email' => 'carlos@oxilive.com.mx', 'role' => 'Administrador'],
        ];

        foreach ($users as $userData) {
            $employee = Employee::query()->where('email', $userData['email'])->firstOrFail();
            $role = Role::query()->where('name', $userData['role'])->firstOrFail();

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'employee_id' => $employee->id,
                    'name' => $userData['name'],
                    'password' => Hash::make('1234567890'),
                    'role_id' => $role->id,
                    'is_active' => true,
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->permissions()->sync([]);
        }
    }
}
