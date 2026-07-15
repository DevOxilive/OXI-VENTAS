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
                'name' => 'Cliente mostrador',
                'phone' => '0000000000',
                'email' => 'mostrador@oxilive.com.mx',
                'active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Restaurante La Terraza',
                'phone' => '5511023344',
                'email' => 'compras@laterraza.mx',
                'active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Abarrotes San Miguel',
                'phone' => '5522046688',
                'email' => 'sanmiguel.compras@example.com',
                'active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Cafeteria Punto Lago',
                'phone' => '5533067799',
                'email' => 'administracion@puntolago.mx',
                'active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Hotel Ajusco Express',
                'phone' => '5544089911',
                'email' => 'operaciones@ajuscoexpress.mx',
                'active' => true,
            ],
            [
                'id' => 6,
                'name' => 'Eventos Cecilia',
                'phone' => '5555102244',
                'email' => 'eventos.cecilia@example.com',
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
