<?php

namespace Database\Seeders;

use App\Models\ProductDepartment;
use Illuminate\Database\Seeder;

class ProductDepartmentSeeder extends Seeder
{
    public const CATALOG = [
        [
            'name' => 'Alimentos',
            'icon' => '🍞',
            'description' => 'Abarrotes, enlatados, cereales, pastas, harinas, botanas, aceites, condimentos, semillas y productos basicos.',
            'categories' => ['Abarrotes', 'Enlatados', 'Cereales', 'Pastas', 'Harinas', 'Botanas', 'Aceites', 'Condimentos', 'Semillas', 'Productos basicos'],
        ],
        [
            'name' => 'Lacteos y Frescos',
            'icon' => '🥛',
            'description' => 'Cremeria, quesos, yogurt, mantequilla, embutidos, huevos y otros refrigerados.',
            'categories' => ['Cremeria', 'Quesos', 'Yogurt', 'Mantequilla', 'Embutidos', 'Huevos', 'Refrigerados'],
        ],
        [
            'name' => 'Refrescos y Bebidas',
            'icon' => '🥤',
            'description' => 'Refrescos, agua, jugos, bebidas energeticas, isotonicas, cafe, te y bebidas sin alcohol.',
            'categories' => ['Refrescos', 'Agua', 'Jugos', 'Bebidas energeticas', 'Bebidas isotonicas', 'Cafe', 'Te', 'Bebidas sin alcohol'],
        ],
        [
            'name' => 'Vinos y Licores',
            'icon' => '🍷',
            'description' => 'Vinos, cerveza, tequila, ron, whisky, vodka, brandy, licores y destilados.',
            'categories' => ['Vinos', 'Cerveza', 'Tequila', 'Ron', 'Whisky', 'Vodka', 'Brandy', 'Licores', 'Destilados'],
        ],
        [
            'name' => 'Farmacia',
            'icon' => '💊',
            'description' => 'Medicamentos, vitaminas, primeros auxilios, material de curacion y cuidado de la salud.',
            'categories' => ['Medicamentos', 'Vitaminas', 'Primeros auxilios', 'Material de curacion', 'Cuidado de la salud'],
        ],
        [
            'name' => 'Belleza y Cuidado Personal',
            'icon' => '💄',
            'description' => 'Perfumeria, cosmeticos, cuidado capilar, higiene personal y cuidado corporal.',
            'categories' => ['Perfumeria', 'Cosmeticos', 'Cuidado capilar', 'Higiene personal', 'Cuidado corporal'],
        ],
        [
            'name' => 'Limpieza',
            'icon' => '🧽',
            'description' => 'Jarceria, detergentes, limpiadores, escobas, trapeadores, fibras, bolsas y desechables.',
            'categories' => ['Jarceria', 'Detergentes', 'Limpiadores', 'Escobas', 'Trapeadores', 'Fibras', 'Bolsas', 'Desechables'],
        ],
        [
            'name' => 'Hogar',
            'icon' => '🏠',
            'description' => 'Utensilios de cocina, plasticos, articulos para el hogar y organizacion.',
            'categories' => ['Utensilios de cocina', 'Plasticos', 'Articulos para el hogar', 'Organizacion'],
        ],
        [
            'name' => 'Papeleria',
            'icon' => '📚',
            'description' => 'Utiles escolares, articulos de oficina, cuadernos, escritura, impresion y manualidades.',
            'categories' => ['Utiles escolares', 'Articulos de oficina', 'Cuadernos', 'Escritura', 'Impresion', 'Manualidades'],
        ],
        [
            'name' => 'Jardin y Semillas',
            'icon' => '🌱',
            'description' => 'Semillas, fertilizantes, macetas, herramientas basicas y productos para cultivo.',
            'categories' => ['Semillas', 'Fertilizantes', 'Macetas', 'Herramientas basicas', 'Productos para cultivo'],
        ],
        [
            'name' => 'Mascotas',
            'icon' => '🐶',
            'description' => 'Alimentos, premios, arena, accesorios, higiene y cuidado para mascotas.',
            'categories' => ['Alimentos para mascotas', 'Premios para mascotas', 'Arena para mascotas', 'Accesorios para mascotas', 'Higiene para mascotas', 'Cuidado para mascotas'],
        ],
        [
            'name' => 'Conveniencia',
            'icon' => '🔋',
            'description' => 'Pilas, cigarros, encendedores, cerillos, cargadores basicos y otros articulos de compra rapida o de mostrador.',
            'categories' => ['Pilas', 'Cigarros', 'Encendedores', 'Cerillos', 'Cargadores basicos', 'Compra rapida', 'Mostrador'],
        ],
    ];

    public function run(): void
    {
        foreach (self::CATALOG as $index => $department) {
            ProductDepartment::updateOrCreate(
                ['name' => $department['name']],
                [
                    'icon' => $department['icon'],
                    'description' => $department['description'],
                    'sort_order' => $index + 1,
                    'active' => true,
                ]
            );
        }
    }
}
