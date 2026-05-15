<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'id' => 1,
                'name' => 'GeneralCustomer',
                'phone' => '0000000000',
                'email' => 'general@customer.com',
                'active' => true,
            ],
        ];

        foreach ($customers as $customer) {
            DB::table('customers')->updateOrInsert(
                ['id' => $customer['id']],
                array_merge($customer, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}