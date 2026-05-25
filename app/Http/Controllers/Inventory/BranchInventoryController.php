<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchInventoryController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderInventory($request, null);
    }

    public function show(Request $request, Branch $branch)
    {
        return $this->renderInventory($request, $branch);
    }

    private function renderInventory(Request $request, ?Branch $branch)
    {
        $query = BranchProduct::query()
            ->select([
                'id',
                'branch_id',
                'product_id',
                'price',
                'stock',
                'min_stock',
                'active',
                'name',
                'barcode',
                'category_id',
                'tracks_batches',
                'tracks_expiration',
            ])
            ->where('active', true)
            ->when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'branch:id,name',
                'product:id,name,category_id,sale_price,cost,active',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->withCount([
                'activeBatches as active_batches_count',
            ])
            ->withMin([
                'activeBatches as next_expiration_date',
            ], 'expiration_date')
            ->orderBy('name');

        $baseStatsQuery = BranchProduct::query()
            ->where('active', true)
            ->when($branch, fn($query) => $query->where('branch_id', $branch->id));

        return Inertia::render('Inventory/BranchInventory', [
            'currentBranch' => $branch,

            'branchProductsDB' => $query
                ->paginate((int) $request->input('per_page', 50))
                ->withQueryString(),

            'inventoryStats' => [
                'total_products' => (clone $baseStatsQuery)->count(),
                'total_stock' => (clone $baseStatsQuery)->sum('stock'),

                'low_stock' => (clone $baseStatsQuery)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->where('stock', '>', 0)
                    ->count(),

                'out_of_stock' => (clone $baseStatsQuery)
                    ->where('stock', '<=', 0)
                    ->count(),

                'inventory_value' => (clone $baseStatsQuery)
                    ->selectRaw('COALESCE(SUM(stock * price), 0) as total')
                    ->value('total'),
            ],

            'productsDB' => Product::query()
                ->select(['id', 'name', 'sale_price', 'cost', 'category_id'])
                ->where('active', true)
                ->orderBy('name')
                ->limit(300)
                ->get(),

            'branchesDB' => Branch::query()
                ->select(['id', 'name'])
                ->where('active', true)
                ->orderBy('name')
                ->get(),

            'filters' => [
                'search' => $request->search,
                'per_page' => (int) $request->input('per_page', 50),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
        ]);

        BranchProduct::create([
            'branch_id' => $validated['branch_id'],
            'product_id' => $validated['product_id'],
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'min_stock' => $validated['min_stock'],
            'active' => true,
        ]);

        return back()->with('success', 'Producto asignado a sucursal correctamente.');
    }
}
