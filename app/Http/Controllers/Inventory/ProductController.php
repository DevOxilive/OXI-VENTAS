<?php

namespace App\Http\Controllers\Inventory;

use App\Events\ProductChanged;
use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\FlexibleSearch;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request, Branch $branch)
    {
        $perPage = $request->integer('per_page', 10);

        $query = BranchProduct::query()
            ->with([
                'branch:id,name,slug',
                'product:id,name,image,description,category_id,cost,sale_price,unit,active,created_at',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->orderByDesc('id');

        if ($request->filled('category_id')) {
            $query->whereHas('product', function ($productQuery) use ($request) {
                $productQuery->where('category_id', $request->category_id);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;

            FlexibleSearch::apply($query, $search, function ($searchQuery, $phrase, $terms) {
                FlexibleSearch::orWhereColumns($searchQuery, [
                    'branch_products.barcode',
                ], $phrase, $terms);

                FlexibleSearch::orWhereHasColumns($searchQuery, 'product', [
                    'name',
                ], $phrase, $terms);

                FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                    'code',
                ], $phrase, $terms);
            });
        }

        $productsDB = $query->paginate($perPage)->withQueryString();

        $productsDB->getCollection()->transform(function ($item) {
            $product = $item->product;

            return [
                'id' => $product?->id,
                'branch_product_id' => $item->id,
                'branch_id' => $item->branch_id,
                'branch_slug' => $item->branch?->slug,

                'branch_ids' => BranchProduct::where('product_id', $product?->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->pluck('branch_id')
                    ->map(fn($id) => (int) $id)
                    ->values(),
                'barcodes' => $product?->barcodes?->pluck('code')->values() ?? [],
                'barcode' => $product?->barcodes?->first()?->code ?? 'Sin código',
                'unit' => $product?->unit ?? '',
                'name' => $product?->name ?? 'Producto sin nombre',
                'image' => $product?->image,
                'category_id' => $product?->category_id,
                'category' => $product?->category?->name ?? 'Sin categoría',

                'min_stock' => $item->min_stock ?? 0,
                'cost' => $product?->cost ?? 0,
                'price' => $product?->sale_price ?? 0,
                'sale_price' => $product?->sale_price ?? 0,
                'salePrice' => $product?->sale_price ?? 0,
                'profit' => number_format(
                    ((float) ($product?->sale_price ?? 0)) - ((float) ($product?->cost ?? 0)),
                    2
                ),
                'active' => $product?->active ?? true,
                'status' => $item->status,
                'tracks_batches' => $item->tracks_batches,
                'tracks_expiration' => $item->tracks_expiration,
                'entry_date' => $item->entry_date
                    ?? optional($item->created_at)->format('Y-m-d')
                    ?? 'Sin fecha',
            ];
        });

        return Inertia::render('Inventory/Home', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ],
            'productsDB' => $productsDB,
            'categoriesDB' => Category::select('id', 'name')
                ->orderBy('name')
                ->get(),
            'branchesDB' => Branch::select('id', 'name', 'slug')
                ->where('active', true)
                ->orderBy('name')
                ->get(),
            'filters' => [
                'search' => $request->search,
                'category_id' => $request->category_id,
                'per_page' => $perPage,
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

            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0', 'gte:cost'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
        ]);

        $barcodes = collect($data['barcodes'] ?? [])
            ->filter(fn($code) => filled($code))
            ->values();

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        [$product, $branchIds] = DB::transaction(function () use ($data, $branch, $imagePath, $barcodes) {
            $product = Product::create([
                'name' => $data['name'],
                'description' => null,
                'image' => $imagePath,
                'cost' => $data['cost'],
                'sale_price' => $data['sale_price'],
                'unit' => $data['unit'],
                'category_id' => $data['category_id'],
                'active' => $data['active'] ?? true,
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

            $branchIds = collect($data['branch_ids'] ?? [])
                ->push($branch->id)
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($branchIds as $branchId) {
                BranchProduct::create([
                    'branch_id' => $branchId,
                    'product_id' => $product->id,
                    'min_stock' => $data['min_stock'] ?? 0,
                    'status' => BranchProduct::STATUS_ACTIVE,
                    'tracks_batches' => false,
                    'tracks_expiration' => false,
                    'entry_date' => $data['entry_date'],
                ]);
            }

            return [$product, $branchIds->all()];
        });

        broadcast(new ProductChanged('created', $product->id, $branchIds))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'el producto', $product->name, 'Inventario', 'created'));

        return back()->with('success', 'Producto creado correctamente');
    }

    public function update(Request $request, Branch $branch, Product $product)
    {
        $data = $request->validate([
            'barcodes' => ['nullable', 'array'],
            'barcodes.*' => ['nullable', 'string', 'max:100', 'distinct'],
            'unit' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
            'cost' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0', 'gte:cost'],
            'entry_date' => ['required', 'date'],
            'active' => ['boolean'],
            'branch_ids' => ['nullable', 'array'],
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

        $previousBranchIds = BranchProduct::where('product_id', $product->id)
            ->pluck('branch_id')
            ->map(fn($id) => (int) $id)
            ->values();

        $branchIds = DB::transaction(function () use ($request, $data, $product, $branch, $barcodes) {
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
                'unit' => $data['unit'],
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

            $branchIds = collect($data['branch_ids'] ?? [])
                ->push($branch->id)
                ->map(fn($id) => (int) $id)
                ->unique()
                ->values();

            foreach ($branchIds as $branchId) {
                BranchProduct::updateOrCreate(
                    [
                        'branch_id' => $branchId,
                        'product_id' => $product->id,
                    ],
                    [
                        'stock' => $data['stock'] ?? 0,
                        'min_stock' => $data['min_stock'] ?? 0,
                        'status' => BranchProduct::STATUS_ACTIVE,
                        'tracks_batches' => false,
                        'tracks_expiration' => false,
                        'entry_date' => $data['entry_date'],
                    ]
                );
            }

            BranchProduct::where('product_id', $product->id)
                ->whereNotIn('branch_id', $branchIds)
                ->update([
                    'status' => BranchProduct::STATUS_INACTIVE,
                ]);

            return $branchIds->all();
        });

        $affectedBranchIds = $previousBranchIds
            ->merge($branchIds)
            ->unique()
            ->values()
            ->all();

        broadcast(new ProductChanged('updated', $product->id, $affectedBranchIds))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'el producto', $product->name, 'Inventario', 'updated'));

        return back()->with('success', 'Producto actualizado correctamente');
    }

    public function destroy(Branch $branch, Product $product)
    {
        $productId = $product->id;
        $productName = $product->name;
        $branchIds = BranchProduct::where('product_id', $product->id)
            ->pluck('branch_id')
            ->map(fn($id) => (int) $id)
            ->values()
            ->all();

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

        broadcast(new ProductChanged('deleted', $productId, $branchIds))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'el producto', $productName, 'Inventario', 'deleted'));

        return back()->with('success', 'Producto eliminado correctamente');
    }
}
