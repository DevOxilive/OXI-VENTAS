<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportInventoryExcel extends Command
{
   protected $signature = 'inventory:import';

    protected $description = 'Importa productos de prueba desde Excel';

    public function handle()
    {
        $path = storage_path('app/imports/RepInventario.xlsx');

        if (!file_exists($path)) {
            $this->error('No existe el archivo Excel.');
            return;
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray();

        $imported = 0;

        foreach ($rows as $index => $row) {

            // Saltar encabezados
            if ($index < 5) {
                continue;
            }

            // SOLO 10 PRODUCTOS
         

            $barcode = trim((string) ($row[0] ?? ''));
            $description = trim((string) ($row[3] ?? ''));
            $cost = (float) preg_replace('/[^0-9.]/', '', $row[8] ?? 0);
            $stock = (float) ($row[9] ?? 0);

            if (!$barcode || !$description || !$cost) {
                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | EXTRAER CATEGORIA
            |--------------------------------------------------------------------------
            */

            $parts = explode('/', $description);

            $categoryName = 'General';

            if (count($parts) > 1) {
                $categoryName = trim($parts[0]);

                // limpiar números
                $categoryName = preg_replace('/^[0-9]+\s*/', '', $categoryName);
            }

            $category = Category::firstOrCreate([
                'name' => ucfirst(strtolower($categoryName))
            ]);

            /*
            |--------------------------------------------------------------------------
            | LIMPIAR NOMBRE
            |--------------------------------------------------------------------------
            */
$name = trim(end($parts));

/*
|--------------------------------------------------------------------------
| LIMPIAR CODIGOS PEGADOS
|--------------------------------------------------------------------------
*/

$name = preg_replace('/\s+[0-9]{6,}$/', '', $name);

/*
|--------------------------------------------------------------------------
| LIMPIAR ESPACIOS
|--------------------------------------------------------------------------
*/

$name = preg_replace('/\s+/', ' ', $name);

            /*
            |--------------------------------------------------------------------------
            | CREAR PRODUCTO
            |--------------------------------------------------------------------------
            */

            $product = Product::create([
                'name' => $name,
                'category_id' => $category->id,
                'active' => true,
            ]);
            $this->info("Producto creado ID: " . $product->id);

            /*
            |--------------------------------------------------------------------------
            | CODIGO DE BARRAS
            |--------------------------------------------------------------------------
            */

            DB::table('barcodes')->insert([
                'product_id' => $product->id,
                'code' => $barcode,
                'type' => 'EAN13',
                'base_quantity' => 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | SUCURSALES
            |--------------------------------------------------------------------------
            */

            foreach (Branch::where('active', true)->get() as $branch) {

                BranchProduct::create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'stock' => $stock,
                    'cost' => $cost,
                    'price' => round($cost * 1.30, 2),
                    'min_stock' => 5,
                    'entry_date' => now()->toDateString(),
                    'active' => true,
                ]);
            }

            $imported++;

            $this->info("Importado: {$name}");
        }

        $this->line('');
        $this->info('Products en DB: ' . Product::count());
$this->info('Barcodes en DB: ' . DB::table('barcodes')->count());
$this->info('Branch products en DB: ' . BranchProduct::count());
        $this->info("TOTAL IMPORTADOS: {$imported}");
    }
}