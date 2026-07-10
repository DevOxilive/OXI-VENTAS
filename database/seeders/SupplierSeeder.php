<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'id' => 1,
                'name' => 'Coca-Cola FEMSA',
                'phone' => '5512345678',
                'email' => 'pedidos@coca-colafemsa.mx',
                'address' => 'Cedis Vallejo, Ciudad de Mexico',
                'active' => true,
            ],
            [
                'id' => 2,
                'name' => 'PepsiCo Alimentos Mexico',
                'phone' => '5587654321',
                'email' => 'mayoreo@pepsico.mx',
                'address' => 'Cedis Tlalnepantla, Estado de Mexico',
                'active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Grupo Lala Distribucion',
                'phone' => '5555012233',
                'email' => 'ventas.cdmx@lala.mx',
                'address' => 'Cedis Iztapalapa, Ciudad de Mexico',
                'active' => true,
            ],
            [
                'id' => 4,
                'name' => 'La Europea Mayoreo',
                'phone' => '5555443322',
                'email' => 'mayoreo@laeuropea.com.mx',
                'address' => 'Naucalpan, Estado de Mexico',
                'active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Alen del Norte',
                'phone' => '5588112233',
                'email' => 'distribucion@alen.com.mx',
                'address' => 'Cedis Cuautitlan, Estado de Mexico',
                'active' => true,
            ],
            [
                'id' => 6,
                'name' => 'Abarrotera Central',
                'phone' => '5522110099',
                'email' => 'pedidos@abarroteracentral.mx',
                'address' => 'Central de Abasto, Ciudad de Mexico',
                'active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            DB::table('suppliers')->updateOrInsert(
                ['id' => $supplier['id']],
                array_merge($supplier, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
