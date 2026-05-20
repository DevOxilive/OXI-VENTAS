<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubcategorySeeder extends Seeder
{
    public function run(): void
    {
        $subcategories = [
            // Abarrotes
            ['category_id' => 1, 'name' => 'Bebidas'],
            ['category_id' => 1, 'name' => 'Botanas'],
            ['category_id' => 1, 'name' => 'Lácteos'],
            ['category_id' => 1, 'name' => 'Enlatados'],
            ['category_id' => 1, 'name' => 'Cereales'],
            ['category_id' => 1, 'name' => 'Dulces'],
            ['category_id' => 1, 'name' => 'Limpieza'],
            ['category_id' => 1, 'name' => 'Higiene personal'],
            ['category_id' => 1, 'name' => 'Panadería'],
            ['category_id' => 1, 'name' => 'Desechables'],

            // Farmacia
            ['category_id' => 2, 'name' => 'Medicamentos'],
            ['category_id' => 2, 'name' => 'Vitaminas'],
            ['category_id' => 2, 'name' => 'Curación'],
            ['category_id' => 2, 'name' => 'Jarabes'],

            // Mascotas
            ['category_id' => 3, 'name' => 'Croquetas'],
            ['category_id' => 3, 'name' => 'Arena'],
            ['category_id' => 3, 'name' => 'Premios'],

            // Papelería
            ['category_id' => 4, 'name' => 'Cuadernos'],
            ['category_id' => 4, 'name' => 'Plumas'],
            ['category_id' => 4, 'name' => 'Oficina'],

            // Hogar
            ['category_id' => 5, 'name' => 'Cocina'],
            ['category_id' => 5, 'name' => 'Decoración'],
            ['category_id' => 5, 'name' => 'Organización'],
        ];

        foreach ($subcategories as $subcategory) {
            DB::table('subcategories')->updateOrInsert(
                [
                    'category_id' => $subcategory['category_id'],
                    'name' => $subcategory['name'],
                ],
                [
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}