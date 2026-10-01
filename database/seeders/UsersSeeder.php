<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    private const BASE_PASSWORD = '123123123';

    public function run(): void
    {
        $basePassword = env('SEED_BASE_USER_PASSWORD');

        if (! $basePassword) {
            if (app()->isProduction()) {
                throw new \RuntimeException('SEED_BASE_USER_PASSWORD es obligatorio para ejecutar UsersSeeder en produccion.');
            }

            $basePassword = self::BASE_PASSWORD;
        }

        $password = Hash::make((string) $basePassword);
        $users = [
            [
                'name' => 'Doctor Carlos',
                'email' => 'carlos@oxilive.com.mx',
                'role' => 'Administrador',
                'first_name' => 'Doctor',
                'last_name' => 'Carlos',
                'department' => 'Administracion',
                'position' => 'Administrador general',
            ],
            [
                'name' => 'Patria',
                'email' => 'patria@oxilive.com.mx',
                'role' => 'Recursos Humanos',
                'first_name' => 'Patria',
                'last_name' => 'Mendoza',
                'department' => 'Recursos Humanos',
                'position' => 'Auxiliar RH',
            ],
        ];

        $privateSuperAdministrator = $this->privateSuperAdministrator();

        if ($privateSuperAdministrator) {
            $users[] = $privateSuperAdministrator;
        }

        $roleIdsByName = DB::table('roles')
            ->whereIn('name', collect($users)->pluck('role')->unique()->all())
            ->pluck('id', 'name');

        foreach ($users as $userData) {
            $roleId = $roleIdsByName[$userData['role']] ?? null;

            if (! $roleId) {
                throw new \RuntimeException("No existe el rol base {$userData['role']}.");
            }

            $employee = $this->employeeFor($userData);
            $userPassword = $userData['password'] ?? $password;

            $user = User::withTrashed()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'employee_id' => $employee->id,
                    'name' => $userData['name'],
                    'password' => $userPassword,
                    'role_id' => $roleId,
                    'branch_id' => null,
                    'is_active' => true,
                ],
            );

            if ($user->trashed()) {
                $user->restore();
            }

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->permissions()->sync([]);
            $user->branches()->sync([]);
        }
    }

    private function privateSuperAdministrator(): ?array
    {
        $email = env('PRIVATE_SUPER_ADMIN_EMAIL');
        $password = env('PRIVATE_SUPER_ADMIN_PASSWORD');

        if (! $email && ! $password) {
            return null;
        }

        if (! $email || ! $password || strlen((string) $password) < 12) {
            throw new \RuntimeException('PRIVATE_SUPER_ADMIN_EMAIL y PRIVATE_SUPER_ADMIN_PASSWORD son obligatorios para sembrar un Super Administrador privado; la contraseña debe tener al menos 12 caracteres.');
        }

        return [
            'name' => env('PRIVATE_SUPER_ADMIN_NAME') ?: 'Super Administrador',
            'email' => $email,
            'role' => 'Super Administrador',
            'first_name' => env('PRIVATE_SUPER_ADMIN_FIRST_NAME') ?: 'Super',
            'last_name' => env('PRIVATE_SUPER_ADMIN_LAST_NAME') ?: 'Administrador',
            'department' => env('PRIVATE_SUPER_ADMIN_DEPARTMENT') ?: 'Sistemas',
            'position' => env('PRIVATE_SUPER_ADMIN_POSITION') ?: 'Super Administrador',
            'password' => Hash::make((string) $password),
        ];
    }

    private function employeeFor(array $userData): Employee
    {
        DB::table('departments')->updateOrInsert(
            ['name' => $userData['department']],
            [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $departmentId = DB::table('departments')
            ->where('name', $userData['department'])
            ->value('id');

        DB::table('positions')->updateOrInsert(
            [
                'name' => $userData['position'],
                'department_id' => $departmentId,
            ],
            [
                'description' => 'Puesto base para acceso inicial al sistema.',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $positionId = DB::table('positions')
            ->where('name', $userData['position'])
            ->where('department_id', $departmentId)
            ->value('id');

        $employee = Employee::withTrashed()->updateOrCreate(
            ['email' => $userData['email']],
            [
                'first_name' => $userData['first_name'],
                'last_name' => $userData['last_name'],
                'employment_status' => 'Activo',
                'start_date' => now()->toDateString(),
                'phone' => null,
                'position_id' => $positionId,
            ],
        );

        if ($employee->trashed()) {
            $employee->restore();
        }

        return $employee;
    }
}
