<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('email', 'brayan@oxilive.com.mx')
            ->delete();

        $users = [
            ['name' => 'Kevin', 'email' => 'kevin@oxilive.com.mx', 'role' => 'Sistemas', 'branches' => []],
            ['name' => 'Asael', 'email' => 'asael@oxilive.com.mx', 'role' => 'Super Administrador', 'branches' => 'all'],
            ['name' => 'Ana', 'email' => 'ana.lilia@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Ajusco', 'Diana']],
            ['name' => 'Laura', 'email' => 'laura@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Lago', 'Cecilia']],
            ['name' => 'Blanca', 'email' => 'blanca@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Ajusco']],
            ['name' => 'Diana', 'email' => 'diana@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Diana']],
            ['name' => 'Rodrigo', 'email' => 'rodrigo@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Lago']],
            ['name' => 'Tono', 'email' => 'tono@oxilive.com.mx', 'role' => 'Inventario', 'branches' => ['Cecilia']],
            ['name' => 'Margarita', 'email' => 'margarita@oxilive.com.mx', 'role' => 'Ventas', 'branches' => ['Ajusco', 'Diana']],
            ['name' => 'Mairani', 'email' => 'mairani@oxilive.com.mx', 'role' => 'Ventas', 'branches' => ['Lago', 'Cecilia']],
            ['name' => 'Patria', 'email' => 'patria@oxilive.com.mx', 'role' => 'Recursos Humanos', 'branches' => []],
            ['name' => 'Doctor Carlos', 'email' => 'carlos@oxilive.com.mx', 'role' => 'Administrador', 'branches' => 'all'],
            ['name' => 'Adriana Cuevas', 'email' => 'adriana.cuevas@oxilive.com.mx', 'role' => 'Super Administrador', 'branches' => 'all'],
        ];

        $branchIdsByName = Branch::query()
            ->where('active', true)
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id);
        $allBranchIds = $branchIdsByName->values()->all();

        foreach ($users as $userData) {
            $employee = Employee::query()->where('email', $userData['email'])->firstOrFail();
            $role = Role::query()->where('name', $userData['role'])->firstOrFail();

            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'employee_id' => $employee->id,
                    'name' => $userData['name'],
                    'password' => Hash::make('123123123'),
                    'role_id' => $role->id,
                    'branch_id' => $this->primaryBranchId($userData['branches'], $branchIdsByName),
                    'is_active' => true,
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();

            // Los permisos directos se limpian para que el rol sea la fuente real.
            $user->permissions()->sync([]);
            $user->branches()->sync($this->branchIdsForUser($userData['branches'], $branchIdsByName, $allBranchIds));
        }
    }

    private function branchIdsForUser(array|string $branches, $branchIdsByName, array $allBranchIds): array
    {
        if ($branches === 'all') {
            return $allBranchIds;
        }

        return collect($branches)
            ->map(fn (string $branchName) => $branchIdsByName[$branchName] ?? null)
            ->filter()
            ->values()
            ->all();
    }

    private function primaryBranchId(array|string $branches, $branchIdsByName): ?int
    {
        if ($branches === 'all' || $branches === []) {
            return null;
        }

        $firstBranchName = collect($branches)->first();

        return $firstBranchName ? ($branchIdsByName[$firstBranchName] ?? null) : null;
    }
}
