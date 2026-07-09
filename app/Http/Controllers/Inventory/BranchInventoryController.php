<?php

namespace App\Http\Controllers\Inventory;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchInventoryController extends Controller
{
    use AuthorizesBranchAccess;

    public function index(Request $request)
    {
        return $this->renderInventory($request, null);
    }

    public function show(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->renderInventory($request, $branch);
    }

    private function renderInventory(Request $request, ?Branch $branch)
    {
        $today = now()->toDateString();
        $nearExpirationLimit = now()->addDays(30)->toDateString();
        $perPage = TablePagination::resolvePerPage($request, 50);

        $query = BranchProduct::query()
            ->select([
                'branch_products.id',
                'branch_products.branch_id',
                'branch_products.product_id',
                'branch_products.barcode',
                'branch_products.stock',
                'branch_products.min_stock',
                'branch_products.status',
                'branch_products.season_start_date',
                'branch_products.season_end_date',
                'branch_products.last_restocked_at',
                'branch_products.inactive_candidate_after_days',
                'branch_products.tracks_batches',
                'branch_products.tracks_expiration',
            ])
            ->when($branch, fn($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('search'), function ($query) use ($request) {
                FlexibleSearch::apply($query, $request->search, function ($searchQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($searchQuery, [
                        'branch_products.barcode',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product', [
                        'name',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                        'code',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'activeBatches', [
                        'lot_number',
                    ], $phrase, $terms);
                });
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                $query->whereHas('product', function ($query) use ($request) {
                    $query->where('category_id', $request->category);
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->when($request->filled('stock'), function ($query) use ($request) {
                match ($request->stock) {
                    'Disponible' => $query->whereColumn('stock', '>', 'min_stock'),
                    'Stock bajo' => $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0),
                    'Agotado' => $query->where('stock', '<=', 0),
                    default => null,
                };
            })
            ->when($request->filled('expiration_status'), function ($query) use ($request, $today, $nearExpirationLimit) {
                match ($request->expiration_status) {
                    'expired' => $query->whereHas('activeBatches', fn($query) => $query
                        ->whereDate('expiration_date', '<', $today)),

                    'near_expiration' => $query->whereHas('activeBatches', fn($query) => $query
                        ->whereDate('expiration_date', '>=', $today)
                        ->whereDate('expiration_date', '<=', $nearExpirationLimit)),

                    'valid' => $query->whereHas('activeBatches', fn($query) => $query
                        ->whereDate('expiration_date', '>', $nearExpirationLimit)),

                    'without_expiration' => $query->whereDoesntHave('activeBatches', fn($query) => $query
                        ->whereNotNull('expiration_date')),

                    default => null,
                };
            })
            ->when($request->filled('inactive_candidate'), function ($query) {
                $query->whereNotNull('last_restocked_at')
                    ->whereRaw('DATE_ADD(last_restocked_at, INTERVAL inactive_candidate_after_days DAY) <= NOW()');
            })
            ->with([
                'branch:id,name',
                'product:id,name,category_id,sale_price,cost,unit',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->withCount([
                'activeBatches as active_batches_count',
            ])
            ->withMin([
                'activeBatches as next_expiration_date',
            ], 'expiration_date')
            ->orderBy('products.name');

        $baseStatsQuery = BranchProduct::query()
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->when($branch, fn($query) => $query->where('branch_id', $branch->id));

        $batchAlertsQuery = ProductBatch::query()
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->whereHas('branchProduct', function ($query) use ($branch) {
                $query->where('status', BranchProduct::STATUS_ACTIVE)
                    ->when($branch, fn($query) => $query->where('branch_id', $branch->id));
            });

        $inactiveCandidateProductsQuery = (clone $baseStatsQuery)
            ->whereNotNull('last_restocked_at')
            ->whereRaw('DATE_ADD(last_restocked_at, INTERVAL inactive_candidate_after_days DAY) <= NOW()');

        $mapBatchAlert = function ($batch) {
            $branchProduct = $batch->branchProduct;
            $product = $branchProduct?->product;

            $productName = collect([
                $product?->name,
                $product?->barcodes?->first()?->code,
            ])->first(fn($value) => filled($value));

            $productCode = collect([
                $product?->barcodes?->first()?->code,
                $product?->barcodes?->first()?->code,
                "BP-{$batch->branch_product_id}",
            ])->first(fn($value) => filled($value));

            return [
                'id' => $batch->id,
                'branch_product_id' => $batch->branch_product_id,
                'lot_number' => $batch->lot_number,
                'expiration_date' => $batch->expiration_date,
                'formatted_expiration_date' => $batch->formatted_expiration_date,
                'quantity' => $batch->quantity,
                'unit' => $product?->unit ?? 'piezas',
                'initial_quantity' => $batch->initial_quantity,
                'status' => $batch->status,
                'expiration_status' => $batch->expiration_status,
                'expiration_human_text' => $batch->expiration_human_text,
                'product_name' => $productName ?: 'Producto sin nombre',
                'product_code' => $productCode,
                'branch_product' => $branchProduct,
            ];
        };

        $mapLowStockProduct = function ($branchProduct) {
            $product = $branchProduct->product;

            $productName = collect([
                $product?->name,
                $product?->barcodes?->first()?->code,
            ])->first(fn($value) => filled($value));

            $productCode = collect([
                $product?->barcodes?->first()?->code,
                $product?->barcodes?->first()?->code,
                "BP-{$branchProduct->id}",
            ])->first(fn($value) => filled($value));

            return [
                'id' => $branchProduct->id,
                'product_id' => $branchProduct->product_id,
                'name' => $productName ?: 'Producto sin nombre',
                'code' => $productCode,
                'stock' => $branchProduct->stock,
                'unit' => $product?->unit ?? 'piezas',
                'minStock' => $branchProduct->min_stock,
                'min_stock' => $branchProduct->min_stock,
                'status' => 'Stock bajo',
                'product' => $product,
                'branch_product' => $branchProduct,
            ];
        };

        $mapInactiveCandidateProduct = function ($branchProduct) use ($mapLowStockProduct) {
            return array_merge($mapLowStockProduct($branchProduct), [
                'status' => 'Producto sin rotacion',
                'last_restocked_at' => $branchProduct->last_restocked_at,
                'inactive_candidate_after_days' => $branchProduct->inactive_candidate_after_days,
            ]);
        };

        $expiredBatchesList = (clone $batchAlertsQuery)
            ->with(['branchProduct.product.barcodes'])
            ->whereDate('expiration_date', '<', $today)
            ->orderBy('expiration_date')
            ->limit(50)
            ->get()
            ->map($mapBatchAlert)
            ->values();

        $nearExpirationBatchesList = (clone $batchAlertsQuery)
            ->with(['branchProduct.product.barcodes'])
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $nearExpirationLimit)
            ->orderBy('expiration_date')
            ->limit(50)
            ->get()
            ->map($mapBatchAlert)
            ->values();

        $lowStockProductsList = (clone $baseStatsQuery)
            ->with(['product.barcodes'])
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->orderBy('stock')
            ->limit(50)
            ->get()
            ->map($mapLowStockProduct)
            ->values();

        $inactiveCandidateProductsList = (clone $inactiveCandidateProductsQuery)
            ->with(['product.barcodes'])
            ->orderBy('last_restocked_at')
            ->limit(50)
            ->get()
            ->map($mapInactiveCandidateProduct)
            ->values();

        return Inertia::render('Inventory/BranchInventory', [
            'currentBranch' => $branch,

            'branchProductsDB' => $query
                ->paginate($perPage)
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

                'expiring_soon' => (clone $batchAlertsQuery)
                    ->whereDate('expiration_date', '>=', $today)
                    ->whereDate('expiration_date', '<=', $nearExpirationLimit)
                    ->count(),

                'inactive_candidates' => (clone $inactiveCandidateProductsQuery)->count(),

                'inventory_value' => (clone $baseStatsQuery)
                    ->join('products', 'products.id', '=', 'branch_products.product_id')
                    ->selectRaw('COALESCE(SUM(branch_products.stock * products.sale_price), 0) as total')
                    ->value('total'),
            ],

            'inventoryAlerts' => [
                'expired_batches' => (clone $batchAlertsQuery)
                    ->whereDate('expiration_date', '<', $today)
                    ->count(),

                'near_expiration_batches' => (clone $batchAlertsQuery)
                    ->whereDate('expiration_date', '>=', $today)
                    ->whereDate('expiration_date', '<=', $nearExpirationLimit)
                    ->count(),

                'low_stock_products' => (clone $baseStatsQuery)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->where('stock', '>', 0)
                    ->count(),

                'inactive_candidate_products' => (clone $inactiveCandidateProductsQuery)->count(),

                'expired_batches_list' => $expiredBatchesList,
                'near_expiration_batches_list' => $nearExpirationBatchesList,
                'low_stock_products_list' => $lowStockProductsList,
                'inactive_candidate_products_list' => $inactiveCandidateProductsList,
            ],

            'productsDB' => Product::query()
                ->select(['products.id', 'products.name', 'products.sale_price', 'products.cost', 'products.unit', 'products.category_id'])
                ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
                ->when($branch, fn ($query) => $query->where('branch_products.branch_id', $branch->id))
                ->where('products.active', true)
                ->with(['category:id,name', 'barcodes:id,product_id,code'])
                ->distinct()
                ->orderBy('products.name')
                ->limit(300)
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sale_price' => $product->sale_price,
                    'cost' => $product->cost,
                    'unit' => $product->unit,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'main_barcode' => $product->barcodes?->first()?->code,
                    'label' => trim($product->name . ' - ' . ($product->barcodes?->first()?->code ?? 'Sin codigo')),
                ])
                ->values(),

            'branchesDB' => Branch::query()
                ->select(['id', 'name', 'slug'])
                ->where('active', true)
                ->orderBy('name')
                ->get(),

            'categoriesDB' => $this->categoryOptions($branch),

            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'status' => $request->status,
                'stock' => $request->stock,
                'expiration_status' => $request->expiration_status,
                'inactive_candidate' => $request->inactive_candidate,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function categoryOptions(?Branch $branch)
    {
        return Category::query()
            ->select(['categories.id', 'categories.name'])
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->when($branch, fn ($query) => $query->where('branch_products.branch_id', $branch->id))
            ->distinct()
            ->orderBy('categories.name')
            ->get();
    }

    public function details(BranchProduct $branchProduct): JsonResponse
    {
        $branchProduct->load([
            'branch:id,name,slug',
            'product:id,name,category_id,sale_price,cost,unit',
            'product.category:id,name',
            'product.barcodes:id,product_id,code',
            'batches' => fn($query) => $query
                ->select([
                    'id',
                    'branch_product_id',
                    'lot_number',
                    'expiration_date',
                    'initial_quantity',
                    'quantity',
                    'supplier',
                    'received_at',
                    'status',
                    'season_start_date',
                    'season_end_date',
                ])
                ->where('quantity', '>', 0)
                ->orderByRaw('expiration_date IS NULL')
                ->orderBy('expiration_date')
                ->orderBy('id'),
            'movements' => fn($query) => $query
                ->select([
                    'id',
                    'branch_product_id',
                    'user_id',
                    'type',
                    'reason',
                    'quantity',
                    'previous_stock',
                    'new_stock',
                    'notes',
                    'created_at',
                ])
                ->with([
                    'user:id,name',
                    'batches:id,stock_movement_id,product_batch_id,quantity',
                    'batches.productBatch:id,lot_number',
                ])
                ->latest(),
        ]);

        return response()->json($branchProduct);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'stock' => ['required', 'numeric', 'min:0'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,seasonal'],
        ]);

        $branchProduct = BranchProduct::create([
            'branch_id' => $validated['branch_id'],
            'product_id' => $validated['product_id'],
            'stock' => $validated['stock'],
            'min_stock' => $validated['min_stock'],
            'status' => $validated['status'] ?? BranchProduct::STATUS_ACTIVE,
            'last_restocked_at' => null,
            'inactive_candidate_after_days' => 90,
        ]);

        event(new InventoryStockUpdated($branchProduct));
        event(RealtimeActivityLogged::message(
            'asigno',
            'el producto a una sucursal',
            $branchProduct->product?->name,
            'Inventario',
            'assigned',
        ));

        return back()->with('success', 'Producto asignado a sucursal correctamente.');
    }

    public function updateConfig(Request $request, BranchProduct $branchProduct)
    {
        $validated = $request->validate([
            'min_stock' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive,seasonal'],
            'season_start_date' => ['nullable', 'date', 'required_if:status,seasonal'],
            'season_end_date' => ['nullable', 'date', 'required_if:status,seasonal', 'after_or_equal:season_start_date'],
        ]);

        $branchProduct->update([
            'min_stock' => $validated['min_stock'],
            'status' => $validated['status'],
            'season_start_date' => $validated['status'] === 'seasonal'
                ? $validated['season_start_date']
                : null,

            'season_end_date' => $validated['status'] === 'seasonal'
                ? $validated['season_end_date']
                : null,
        ]);

        event(new InventoryStockUpdated($branchProduct));
        event(RealtimeActivityLogged::message(
            'actualizo',
            'la configuracion de inventario del producto',
            $branchProduct->product?->name,
            'Inventario',
            'configured',
        ));

        return back()->with('success', 'Configuracion del producto actualizada correctamente.');
    }
}
