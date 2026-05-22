<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Subcategory;
use Illuminate\Support\Facades\DB;
use App\Models\Branch;
use App\Models\BranchProduct;

class ProductController extends Controller
{
public function index(Branch $branch)
{
    return Inertia::render('Inventory/Home', [
        'branch' => [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
        ],

        'productsDB' => BranchProduct::with([
            'branch',
            'product.category',
            'product.barcodes',
        ])
            ->where('branch_id', $branch->id)
            ->where('active', true)
            ->get()
            ->map(function ($item) {
                $product = $item->product;

                return [
                    'id' => $product->id,
                    'branch_product_id' => $item->id,
                    'barcode' => $item->barcode
        ?? $product?->barcodes?->first()?->code
        ?? 'Sin código',
                    'name' => $item->name,
                    'image' => $product->image ? asset('storage/' . $product->image) : null,
                    'presentation' => $product->description,
                    'category_id' => $item->category_id,
                    'category_name' => $product->category?->name ?? 'No category',
                    'branch_id' => $item->branch_id,
                    'branch_name' => $item->branch?->name ?? 'General',
                    'branch_slug' => $item->branch?->slug,
                    'stock' => $item->stock,
                    'cost' => number_format($item->cost, 2),
                    'price' => number_format($item->price, 2),
                    'profit' => number_format($item->price - $item->cost, 2),
               
'entry_date' => $item->entry_date
    ?? optional($item->created_at)->format('Y-m-d')
    ?? 'Sin fecha',
                ];
            }),

        'categoriesDB' => Category::select('id', 'name')->get(),
        'subcategoriesDB' => Subcategory::select('id', 'category_id', 'name')->get(),
        'branchesDB' => Branch::select('id', 'name', 'slug')->get(),
    ]);
}
public function store(Request $request, Branch $branch)
{
    $data = $request->validate([
        'barcode' => ['nullable', 'string', 'max:100', 'unique:barcodes,code'],
        'name' => ['required', 'string', 'max:255'],
        'image' => ['nullable', 'image', 'max:2048'],
        'stock' => ['required', 'numeric', 'min:0'],
        'category_id' => ['required', 'exists:categories,id'],
        'cost' => ['required', 'numeric', 'min:0'],
        'sale_price' => ['required', 'numeric', 'min:0'],
        'entry_date' => ['required', 'date'],
        'active' => ['boolean'],
    ]);

    $imagePath = null;

    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('products', 'public');
    }

    DB::transaction(function () use ($data, $branch, $imagePath) {
        $product = Product::create([
            'name' => $data['name'],
            'description' => null,
            'image' => $imagePath,
            'cost' => $data['cost'],
            'sale_price' => $data['sale_price'],
            'category_id' => $data['category_id'],
            'subcategory_id' => null,
            'active' => true,
        ]);

        if (!empty($data['barcode'])) {
            DB::table('barcodes')->insert([
                'product_id' => $product->id,
                'code' => $data['barcode'],
                'type' => 'EAN13',
                'base_quantity' => 1,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        BranchProduct::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'barcode' => $data['barcode'],
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'stock' => $data['stock'],
            'cost' => $data['cost'],
            'price' => $data['sale_price'],
            'min_stock' => 0,
            'entry_date' => $data['entry_date'],
            'active' => true,
        ]);
    });

    return back()->with('success', 'Producto creado correctamente');
}
       public function update(Request $request, Branch $branch, Product $product)
    {
        $barcodeId = optional($product->barcodes()->first())->id;

        $data = $request->validate([
            'barcode' => ['nullable', 'string', 'max:100', 'unique:barcodes,code,' . $barcodeId],
            'name' => ['required', 'string', 'max:255'],
            'stock' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
        ]);

        DB::transaction(function () use ($data, $product, $branch) {
            $product->update([
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'active' => $data['active'] ?? true,
            ]);

            if (!empty($data['barcode'])) {
                DB::table('barcodes')->updateOrInsert(
                    ['product_id' => $product->id],
                    [
                        'code' => $data['barcode'],
                        'type' => 'EAN13',
                        'base_quantity' => 1,
                        'active' => true,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            BranchProduct::updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                ],
                [
                    'barcode' => $data['barcode'],
                    'name' => $data['name'],
                    'category_id' => $data['category_id'],
                    'stock' => $data['stock'],
                    'cost' => $data['cost'],
                    'price' => $data['sale_price'],
                    'entry_date' => $data['entry_date'],
                    'active' => $data['active'] ?? true,
                ]
            );
        });

        return back()->with('success', 'Producto actualizado correctamente');
    }
public function destroy(Branch $branch, Product $product)
{
    BranchProduct::where('branch_id', $branch->id)
        ->where('product_id', $product->id)
        ->update([
            'active' => false,
        ]);

    return back()->with('success', 'Producto eliminado de esta sucursal correctamente');
}
}