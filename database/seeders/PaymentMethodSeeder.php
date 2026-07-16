<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['id' => 1, 'name' => 'Efectivo', 'active' => true],
            ['id' => 2, 'name' => 'Tarjeta', 'active' => true],
            ['id' => 3, 'name' => 'Transferencia', 'active' => false],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->updateOrInsert(
                ['id' => $method['id']],
                array_merge($method, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
