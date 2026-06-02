<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Branch;
use App\Models\BranchProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request, Branch $branch)
    {
        $query = BranchProduct::query()
            ->with([
                'branch:id,name,slug',
                'product:id,name,image,description,category_id,subcategory_id,cost,sale_price,active,created_at',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('active', true)
            ->orderByDesc('id');

        return Inertia::render('Inventory/Home', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,

            ],

            'productsDB' => $query
                ->get()
                ->map(function ($item) {
                    $product = $item->product;

                    return [
                        'id' => $product?->id,
                        'branch_ids' => BranchProduct::where('product_id', $product?->id)
                            ->where('active', true)
                            ->pluck('branch_id')
                            ->toArray(),
                        'branch_product_id' => $item->id,
                        'barcodes' => $product?->barcodes?->pluck('code')->values() ?? [],

                        'barcode' => $product?->barcodes?->first()?->code
                            ?? $item->barcode
                            ?? 'Sin código',

                        'unit' => $item->unit ?? '',

                        'name' => $product?->name
                            ?? $item->name
                            ?? 'Producto sin nombre',

                        'image' => $product?->image
                            ? asset('storage/' . $product->image)
                            : null,

                        'image_path' => $product?->image,

                        'presentation' => $product?->description,

                        'category_id' => $product?->category_id
                            ?? $item->category_id,

                        'category_name' => $product?->category?->name
                            ?? 'Sin categoría',

                        'branch_id' => $item->branch_id,
                        'branch_name' => $item->branch?->name ?? 'General',
                        'branch_slug' => $item->branch?->slug,

                        'stock' => $item->stock,
                        'min_stock' => $item->min_stock,

                        'cost' => $item->cost ?? $product?->cost ?? 0,
                        'price' => $item->price ?? $product?->sale_price ?? 0,
                        'sale_price' => $item->price ?? $product?->sale_price ?? 0,
                        'salePrice' => $item->price ?? $product?->sale_price ?? 0,





                        'profit' => number_format(
                            ($item->price ?? 0) - ($item->cost ?? $product?->cost ?? 0),
                            2
                        ),

                        'tracks_batches' => $item->tracks_batches,
                        'tracks_expiration' => $item->tracks_expiration,

                        'entry_date' => $item->entry_date
                            ?? optional($item->created_at)->format('Y-m-d')
                            ?? 'Sin fecha',
                    ];
                }),


            'categoriesDB' => Category::select('id', 'name')
                ->orderBy('name')
                ->get(),

            'subcategoriesDB' => Subcategory::select('id', 'category_id', 'name')
                ->orderBy('name')
                ->get(),

            'branchesDB' => Branch::select('id', 'name', 'slug')
                ->orderBy('name')
                ->get(),

            'filters' => [
                'search' => $request->search,
                'per_page' => (int) $request->input('per_page', 50),





            ],
        ]);
    }

    public function store(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'barcodes' => ['nullable', 'array'],
            'barcodes.*' => ['nullable', 'string', 'max:100', 'distinct', 'unique:barcodes,code'],

            'unit' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0', 'gte:cost'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['exists:branches,id'],
        ]);

        $barcodes = collect($data['barcodes'] ?? [])
            ->filter(fn($code) => filled($code))
            ->values();

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        DB::transaction(function () use ($data, $branch, $imagePath, $barcodes) {
            $product = Product::create([
                'name' => $data['name'],
                'description' => null,
                'image' => $imagePath,
                'cost' => $data['cost'],
                'sale_price' => $data['sale_price'],
                'category_id' => $data['category_id'],
                'active' => true,
            ]);

            foreach ($barcodes as $index => $code) {
                DB::table('barcodes')->insert([
                    'product_id' => $product->id,
                    'code' => $code,
                    'type' => $index === 0 ? 'PRINCIPAL' : 'ALTERNO',
                    'base_quantity' => 1,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($data['branch_ids'] as $branchId) {

                BranchProduct::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'barcode' => $barcodes->first(),
                    'name' => $data['name'],
                    'category_id' => $data['category_id'],
                    'unit' => $data['unit'],
                    'stock' => $branchId == $branch->id
                        ? ($data['stock'] ?? 0)
                        : 0,
                    'cost' => $data['cost'],
                    'price' => $data['sale_price'],
                    'min_stock' => 0,
                    'entry_date' => $data['entry_date'],
                    'active' => true,
                ]);
            }
        });

        return back()->with('success', 'Producto creado correctamente');
    }
    public function update(Request $request, Branch $branch, Product $product)
    {
        $data = $request->validate([
            'barcodes' => ['nullable', 'array'],
            'barcodes.*' => [
                'nullable',
                'string',
                'max:100',
                'distinct',
            ],

            'unit' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0', 'gte:cost'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['exists:branches,id'],
        ]);

        $barcodes = collect($data['barcodes'] ?? [])
            ->filter(fn($code) => filled($code))
            ->values();
        $duplicatedBarcode = DB::table('barcodes')
            ->whereIn('code', $barcodes)
            ->where('product_id', '!=', $product->id)
            ->first();

        if ($duplicatedBarcode) {
            return back()->withErrors([
                'barcodes.0' => 'Este código de barras ya pertenece a otro producto.',
            ]);
        }

        DB::transaction(function () use ($request, $data, $product, $branch, $barcodes) {
            $imagePath = $product->image;

            if ($request->hasFile('image')) {
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }

                $imagePath = $request->file('image')->store('products', 'public');
            }

            $product->update([
                'name' => $data['name'],
                'image' => $imagePath,
                'category_id' => $data['category_id'],
                'cost' => $data['cost'],
                'sale_price' => $data['sale_price'],
                'active' => $data['active'] ?? true,
            ]);

            DB::table('barcodes')
                ->where('product_id', $product->id)
                ->delete();

            foreach ($barcodes as $index => $code) {
                DB::table('barcodes')->insert([
                    'product_id' => $product->id,
                    'code' => $code,
                    'type' => $index === 0 ? 'PRINCIPAL' : 'ALTERNO',
                    'base_quantity' => 1,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            BranchProduct::where('product_id', $product->id)
                ->whereNotIn('branch_id', $data['branch_ids'])
                ->update([
                    'active' => false,
                ]);

            foreach ($data['branch_ids'] as $branchId) {
                BranchProduct::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                    ],
                    [
                        'barcode' => $barcodes->first(),
                        'name' => $data['name'],
                        'category_id' => $data['category_id'],
                        'unit' => $data['unit'],
                        'stock' => $branchId == $branch->id ? ($data['stock'] ?? 0) : 0,
                        'cost' => $data['cost'],
                        'price' => $data['sale_price'],
                        'entry_date' => $data['entry_date'],
                        'active' => true,
                    ]
                );
            }
        });

        return back()->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Branch $branch, Product $product)
    {
        DB::transaction(function () use ($branch, $product) {
            BranchProduct::where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->delete();

            $existsInAnotherBranch = BranchProduct::where('product_id', $product->id)
                ->exists();

            if (!$existsInAnotherBranch) {
                $product->barcodes()->delete();

                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }

                $product->delete();
            }
        });

        return back()->with('success', 'Producto eliminado correctamente');
    }
}
