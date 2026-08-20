<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    private const BASE_PASSWORD = '123123123';

    public function run(): void
    {
        $users = [
            [
                'name' => 'Sistemas',
                'email' => 'sistemas@oxilive.com.mx',
                'role' => 'Super Administrador',
                'first_name' => 'Sistemas',
                'last_name' => 'OXI',
                'department' => 'Sistemas',
                'position' => 'Sistemas',
            ],
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

        $allowedEmails = collect($users)->pluck('email')->all();

        User::query()
            ->whereNotIn('email', $allowedEmails)
            ->get()
            ->each(function (User $user): void {
                $user->permissions()->sync([]);
                $user->branches()->sync([]);
                $user->delete();
            });

        foreach ($users as $userData) {
            $role = Role::query()
                ->where('name', $userData['role'])
                ->firstOrFail();
            $employee = $this->employeeFor($userData);

            $user = User::withTrashed()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'employee_id' => $employee->id,
                    'name' => $userData['name'],
                    'password' => Hash::make(self::BASE_PASSWORD),
                    'role_id' => $role->id,
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
