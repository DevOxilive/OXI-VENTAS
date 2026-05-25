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
    public function index(Request $request, Branch $branch)
    {
        $query = BranchProduct::query()
            ->select([
                'id',
                'branch_id',
                'product_id',
                'price',
                'stock',
                'min_stock',
                'tracks_batches',
                'tracks_expiration',
                'active',
                'created_at',
            ])
            ->with([
                'branch:id,name,slug',
                'product:id,name,image,description,category_id,subcategory_id,cost,sale_price,active,created_at',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('active', true)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->whereHas('product', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhereHas('barcodes', function ($query) use ($search) {
                            $query->where('code', 'like', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id');

        return Inertia::render('Inventory/Home', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ],

            'productsDB' => $query
                ->paginate((int) $request->input('per_page', 50))
                ->through(function ($item) {
                    $product = $item->product;

                    return [
                        'id' => $product?->id,
                        'branch_product_id' => $item->id,
                        'barcode' => $product?->barcodes?->first()?->code ?? 'Sin código',
                        'name' => $product?->name ?? 'Producto sin nombre',
                        'image' => $product?->image
                            ? asset('storage/' . $product->image)
                            : null,
                        'presentation' => $product?->description,
                        'category_id' => $product?->category_id,
                        'category_name' => $product?->category?->name ?? 'No category',
                        'branch_id' => $item->branch_id,
                        'branch_name' => $item->branch?->name ?? 'General',
                        'branch_slug' => $item->branch?->slug,
                        'stock' => $item->stock,
                        'min_stock' => $item->min_stock,
                        'cost' => number_format($product?->cost ?? 0, 2),
                        'price' => number_format($item->price ?? 0, 2),
                        'salePrice' => number_format($product?->sale_price ?? 0, 2),
                        'profit' => number_format(
                            ($item->price ?? 0) - ($product?->cost ?? 0),
                            2
                        ),
                        'tracks_batches' => $item->tracks_batches,
                        'tracks_expiration' => $item->tracks_expiration,
                        'entry_date' => optional($item->created_at)->format('Y-m-d') ?? 'Sin fecha',
                    ];
                })
                ->withQueryString(),

            'categoriesDB' => Category::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get(),

            'subcategoriesDB' => Subcategory::query()
                ->select(['id', 'category_id', 'name'])
                ->orderBy('name')
                ->get(),

            'branchesDB' => Branch::query()
                ->select(['id', 'name', 'slug'])
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
