<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $methods = [
            ['id' => 1, 'name' => 'Efectivo', 'active' => true],
            ['id' => 2, 'name' => 'Tarjeta', 'active' => true],
            ['id' => 3, 'name' => 'Crédito empleado', 'active' => true],
        ];

        DB::table('payment_methods')->upsert(
            collect($methods)
                ->map(fn (array $method) => array_merge($method, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]))
                ->all(),
            ['id'],
            ['name', 'active', 'updated_at']
        );
    }
}
