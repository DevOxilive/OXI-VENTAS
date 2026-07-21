<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'Administrador')->first();
        $superAdministratorRole = Role::where('name', 'Super Administrador')->first();

        $admin = User::updateOrCreate(
            ['email' => 'admin@oxilive.com.mx'],
            [
                'employee_id' => 1,
                'name' => 'admin',
                'password' => Hash::make('1234567890'),
                'role_id' => $adminRole?->id,
                'is_active' => true,
            ]
        );

        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->permissions()->sync([]);

        $superAdministrator = User::updateOrCreate(
            ['email' => 'superadmin@oxilive.com.mx'],
            [
                'employee_id' => null,
                'name' => 'Superadmin',
                'password' => Hash::make('1234567890'),
                'role_id' => $superAdministratorRole?->id,
                'is_active' => true,
            ]
        );

        $superAdministrator->forceFill(['email_verified_at' => now()])->save();
        $superAdministrator->permissions()->sync([]);
    }
}
