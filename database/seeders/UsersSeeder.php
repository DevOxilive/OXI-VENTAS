<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@oxilive.com.mx'],
            [
                'name' => 'admin',
                'password' => Hash::make('1234567890'),
            ]
        );
    }
}