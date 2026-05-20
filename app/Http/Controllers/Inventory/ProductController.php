<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\BranchInventory;
use App\Models\Subcategory;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
class ProductController extends Controller
{
public function index()
{
    return Inertia::render('Inventory/Home', [

        'productsDB' => Product::with([
            'category',
            'subcategory',
            'barcodes',
        ])
            ->where('active', true)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,

                    'barcode' => $product->barcodes->first()?->code ?? '',

                    'name' => $product->name,
                    'presentation' => $product->description,

                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name ?? 'No category',

                    'subcategory_id' => $product->subcategory_id,
                    'subcategory_name' => $product->subcategory?->name ?? 'No subcategory',

                    'branch_name' => 'General',

                    'stock' => 0,
                    'minimum_stock' => 0,
                    'maximum_stock' => 0,

                    'cost' => number_format($product->cost, 2),
                    'price' => number_format($product->sale_price, 2),
                    'profit' => number_format($product->sale_price - $product->cost, 2),
                ];
            }),

        'categoriesDB' => Category::select('id', 'name')->get(),

        'subcategoriesDB' => Subcategory::select('id', 'category_id', 'name')->get(),

        'branchesDB' => Branch::select('id', 'name')->get(),
    ]);
}
public function store(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string', 'max:255'],

        'cost' => ['required', 'numeric', 'min:0'],
        'sale_price' => ['required', 'numeric', 'min:0'],

        'category_id' => ['required', 'exists:categories,id'],
        'subcategory_id' => ['nullable', 'exists:subcategories,id'],

'barcode' => ['nullable', 'string', 'max:100', 'unique:barcodes,code'],
        'barcode_type' => ['nullable', 'string', 'max:50'],
        'base_quantity' => ['nullable', 'numeric', 'min:1'],

        'initial_stock' => ['nullable', 'numeric', 'min:0'],
        'minimum_stock' => ['nullable', 'numeric', 'min:0'],
        'maximum_stock' => ['nullable', 'numeric', 'min:0'],

        'active' => ['boolean'],
    ]);

    DB::transaction(function () use ($data) {

        $product = Product::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'cost' => $data['cost'],
            'sale_price' => $data['sale_price'],
            'category_id' => $data['category_id'],
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'active' => $data['active'] ?? true,
        ]);

        if (!empty($data['barcode'])) {
            DB::table('barcodes')->insertOrIgnore([
                'product_id' => $product->id,
                'code' => $data['barcode'],
                'type' => $data['barcode_type'] ?? 'EAN13',
                'base_quantity' => $data['base_quantity'] ?? 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $branch = Branch::where('active', true)->first();

        if ($branch) {
            DB::table('branch_inventory')->updateOrInsert(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'current_stock' => $data['initial_stock'] ?? 0,
                    'minimum_stock' => $data['minimum_stock'] ?? 0,
                    'maximum_stock' => $data['maximum_stock'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    });

    return back()->with('success', 'Producto creado correctamente');
}

  public function update(Request $request, Product $product)
{
    $data = $request->validate([

        'name' => ['required', 'string', 'max:255'],

        'description' => ['nullable', 'string', 'max:255'],

        'cost' => ['required', 'numeric', 'min:0'],

        'sale_price' => ['required', 'numeric', 'min:0'],

        'category_id' => ['required', 'exists:categories,id'],

        'subcategory_id' => ['nullable', 'exists:subcategories,id'],

        'active' => ['boolean'],

    ]);

    $product->update($data);

    return back()->with(
        'success',
        'Producto actualizado correctamente'
    );
}

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with(
            'success',
            'Producto eliminado correctamente'
        );
    }
}