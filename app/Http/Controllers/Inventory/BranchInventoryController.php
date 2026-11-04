<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\ProductBatch;
use App\Models\ProductDepartment;
use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use App\Services\InventoryBackgroundContext;
use App\Services\InventoryOutbox;
use App\Services\InventoryReadService;
use App\Support\TablePagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BranchInventoryController extends Controller
{
    use AuthorizesBranchAccess;

    public const INVENTORY_PERMISSIONS = [
        'inventory.branches.view', 'inventory.branches.stock-in', 'inventory.branches.stock-out',
        'inventory.branches.stock-adjust', 'inventory.branches.batches.update', 'inventory.branches.config.update',
    ];

    public function __construct(
        private readonly ProductSearchService $productSearch,
    ) {}

    public function landing(Request $request)
    {
        $branch = $request->user()
            ?->accessibleBranchesQuery()
            ->select(['branches.id', 'branches.name'])
            ->orderBy('branches.name')
            ->first();

        if (! $branch) {
            return $this->redirectToBranchSetup($request, 'inventario');
        }

        return redirect()->route('inventory.branches.inventory', [
            'branch' => $branch->id,
        ]);
    }

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
        $perPage = TablePagination::resolvePerPage($request);
        $canUseInventory = $request->user()->all_permissions->pluck('name')->intersect(self::INVENTORY_PERMISSIONS)->isNotEmpty();
        $summaryPayload = null;
        $summary = function () use ($branch, &$summaryPayload) {
            return $summaryPayload ??= $this->inventorySummaryPayload($branch);
        };

        return Inertia::render('Inventory/BranchInventory', [
            'canUseInventory' => $canUseInventory,
            'currentBranch' => $branch,
            'branchProductsDB' => fn () => $this->inventoryRowsQuery($request, $branch, $canUseInventory)
                ->paginate($perPage)->withQueryString(),
            'inventoryStats' => fn () => $canUseInventory ? $summary()['inventoryStats'] : [],
            'inventoryAlerts' => fn () => $canUseInventory ? $summary()['inventoryAlerts'] : [],

            'branchesDB' => fn () => Branch::query()
                ->select(['id', 'name', 'slug'])
                ->where('active', true)
                ->orderBy('name')
                ->get(),

            'categoriesDB' => fn () => $this->categoryOptions($branch),
            'productDepartmentsDB' => fn () => $this->productDepartmentOptions($branch),
            // Los filtros permanecen acotados a la sucursal. El formulario, en cambio,
            // debe poder elegir cualquier categoría activa del catálogo.
            'catalogCategoriesDB' => fn () => $this->catalogCategoryOptions(),
            'catalogProductDepartmentsDB' => fn () => $this->catalogProductDepartmentOptions(),
            'canManagePricing' => fn () => $request->user()?->hasPermission('inventory.products.manage-pricing') ?? false,

            'filters' => [
                'search' => $request->search,
                'product_department' => $this->selectedIntegerIds($request->input('product_department')),
                'category' => $this->selectedIntegerIds($request->input('category')),
                'stock' => $request->stock,
                'per_page' => $perPage,
            ],
        ]);
    }

    private function inventoryRowsQuery(Request $request, ?Branch $branch, bool $canUseInventory)
    {
        return BranchProduct::query()
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
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id))
            ->when($request->filled('search'), function ($query) use ($request, $branch) {
                $this->productSearch->constrainBranchProducts(
                    $query,
                    (string) $request->search,
                    'branch_products.product_id',
                    'batches',
                    new ProductSearchOptions(
                        branchIds: $branch ? [$branch->id] : [],
                        limit: (int) config('product_search.max_results'),
                        includeLotNumbers: true,
                        lotStatuses: [ProductBatch::STATUS_ACTIVE],
                        onlyLotsWithStock: false,
                        inventorySqlPrefilter: true,
                    ),
                    orderByRelevance: true,
                    resetExistingOrder: true,
                );
            })
            ->when($this->selectedIntegerIds($request->input('category')) !== [], function ($query) use ($request) {
                $categoryIds = $this->selectedIntegerIds($request->input('category'));

                $query->whereHas('product', function ($query) use ($categoryIds) {
                    $query->whereIn('category_id', $categoryIds);
                });
            })
            ->when($this->selectedIntegerIds($request->input('product_department')) !== [], function ($query) use ($request) {
                $productDepartmentIds = $this->selectedIntegerIds($request->input('product_department'));

                $query->whereHas('product.category', function ($query) use ($productDepartmentIds) {
                    $query->whereIn('product_department_id', $productDepartmentIds);
                });
            })
            ->when($canUseInventory && $request->filled('stock'), function ($query) use ($request) {
                match ($request->stock) {
                    'Disponible' => $query->whereColumn('stock', '>', 'min_stock'),
                    'Stock bajo' => $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0),
                    'Agotado' => $query->where('stock', '<=', 0),
                    default => null,
                };
            })
            ->with([
                'branch:id,name',
                'product:id,name,search_aliases,image,category_id,sale_price,cost,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,piece_pricing_mode,box_pricing_mode,piece_margin_percentage,box_margin_percentage,active,updated_at,catalog_version',
                'product.category:id,product_department_id,name',
                'product.category.productDepartment:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            // Quien sólo administra catálogo no debe recibir indicadores de lotes o
            // caducidad en la respuesta Inertia, aunque la tabla no los muestre.
            ->when($canUseInventory, fn ($query) => $query
                ->withCount(['activeBatches as active_batches_count'])
                ->withMin(['activeBatches as next_expiration_date'], 'expiration_date'))
            ->when(! $canUseInventory, fn ($query) => $query->select([
                'branch_products.id', 'branch_products.product_id', 'branch_products.branch_id', 'branch_products.barcode',
            ]))
            ->orderBy('products.name')->orderBy('branch_products.id');

    }

    private function categoryOptions(?Branch $branch)
    {
        return Category::query()
            ->where('active', true)
            ->orderBy('categories.name')
            ->get(['id', 'product_department_id', 'name']);
    }

    private function productDepartmentOptions(?Branch $branch)
    {
        return ProductDepartment::query()
            ->where('product_departments.active', true)
            ->orderBy('product_departments.name')
            ->get(['id', 'name', 'icon']);
    }

    private function catalogProductDepartmentOptions()
    {
        return ProductDepartment::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);
    }

    private function catalogCategoryOptions()
    {
        return Category::query()
            ->with('productDepartment:id,name')
            ->where('active', true)
            ->orderBy('product_department_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'product_department_id', 'name', 'sort_order'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'product_department_id' => $category->product_department_id,
                'name' => $category->name,
                'department_name' => $category->productDepartment?->name,
            ])
            ->values();
    }

    private function selectedIntegerIds($value): array
    {
        return collect(is_array($value) ? $value : (filled($value) ? [$value] : []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function history(Request $request, BranchProduct $branchProduct): JsonResponse
    {
        $this->abortIfUserCannotAccessBranch($request, $branchProduct->branch);

        return response()->json(app(InventoryReadService::class)->movements($branchProduct, $request));
    }

    public function details(Request $request, BranchProduct $branchProduct): JsonResponse
    {
        $branchProduct->loadMissing('branch', 'product:id,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box');
        $this->abortIfUserCannotAccessBranch($request, $branchProduct->branch);

        return response()->json($this->serializeBranchProductDetails($branchProduct, $request));
    }

    public function realtimeSnapshot(Request $request, Branch $branch): JsonResponse
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $validated = $request->validate([
            'branch_product_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'branch_product_ids' => ['sometimes', 'array', 'max:100'],
            'branch_product_ids.*' => ['integer', 'min:1'],
            'product_ids' => ['sometimes', 'array', 'max:100'],
            'product_ids.*' => ['integer', 'min:1'],
            'include_details' => ['sometimes', 'boolean'],
            'details_branch_product_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $branchProductIds = $this->selectedIntegerIds([
            ...($validated['branch_product_ids'] ?? []), $validated['branch_product_id'] ?? null,
            $request->boolean('include_details') ? ($validated['details_branch_product_id'] ?? null) : null,
        ]);
        $productIds = $this->selectedIntegerIds([
            ...($validated['product_ids'] ?? []), $validated['product_id'] ?? null,
        ]);
        $rows = collect();
        if ($branchProductIds !== [] || $productIds !== []) {
            $rows = BranchProduct::query()->where('branch_id', $branch->id)
                ->where(function ($query) use ($branchProductIds, $productIds) {
                    $query->whereIn('id', $branchProductIds)->orWhereIn('product_id', $productIds);
                })
                ->with([
                    'branch:id,name,slug',
                    'product:id,name,search_aliases,image,category_id,sale_price,cost,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,piece_pricing_mode,box_pricing_mode,piece_margin_percentage,box_margin_percentage,active,updated_at,catalog_version',
                    'product.category:id,product_department_id,name',
                    'product.category.productDepartment:id,name',
                    'product.barcodes:id,product_id,code',
                ])
                ->withCount('activeBatches as active_batches_count')
                ->withMin('activeBatches as next_expiration_date', 'expiration_date')
                ->get();
        }

        $branchProduct = isset($validated['branch_product_id'])
            ? $rows->firstWhere('id', (int) $validated['branch_product_id'])
            : (isset($validated['product_id']) ? $rows->firstWhere('product_id', (int) $validated['product_id']) : null);
        $payload = [
            'branchProducts' => $rows->map(fn ($row) => $this->serializeBranchProductRow($row))->values(),
            'missing_branch_product_ids' => array_values(array_diff($branchProductIds, $rows->pluck('id')->all())),
            'missing_product_ids' => array_values(array_diff($productIds, $rows->pluck('product_id')->all())),
        ];
        if (isset($validated['branch_product_id']) || isset($validated['product_id'])) {
            $payload['branchProduct'] = $branchProduct ? $this->serializeBranchProductRow($branchProduct) : null;
        }
        if ($request->boolean('include_details')) {
            $detailProduct = isset($validated['details_branch_product_id'])
                ? $rows->firstWhere('id', (int) $validated['details_branch_product_id'])
                : $branchProduct;
            $payload['branchProductDetails'] = $detailProduct
                ? $this->serializeBranchProductDetails($detailProduct, $request)
                : null;
        }

        return response()->json($payload);
    }

    public function summary(Request $request, Branch $branch): JsonResponse
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $request->validate(['include_alerts' => ['sometimes', 'boolean']]);

        return response()->json($this->inventorySummaryPayload($branch, $request->boolean('include_alerts')));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'stock' => ['required', 'numeric'],
            'min_stock' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:active,inactive,seasonal'],
        ]);

        $branch = Branch::query()->findOrFail($validated['branch_id']);
        $this->abortIfUserCannotAccessBranch($request, $branch);

        app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(function () use ($validated) {
            $branchProduct = BranchProduct::create([
                'branch_id' => $validated['branch_id'],
                'product_id' => $validated['product_id'],
                'stock' => $validated['stock'],
                'min_stock' => $validated['min_stock'],
                'status' => $validated['status'] ?? BranchProduct::STATUS_ACTIVE,
                'last_restocked_at' => null,
                'inactive_candidate_after_days' => 90,
            ]);

            app(InventoryOutbox::class)->stockChanged(
                $branchProduct, 'asigno', 'el producto a una sucursal', 'assigned',
            );
        }));

        return back()->with('success', 'Producto asignado a sucursal correctamente.');
    }

    public function updateConfig(Request $request, BranchProduct $branchProduct)
    {
        $branchProduct->loadMissing('branch', 'product:id,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box');
        $this->abortIfUserCannotAccessBranch($request, $branchProduct->branch);

        $validated = $request->validate([
            'min_stock' => $this->minimumStockRules((string) ($branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit ?? 'pza')),
            'status' => ['required', 'in:active,inactive,seasonal'],
            'season_start_date' => ['nullable', 'date', 'required_if:status,seasonal'],
            'season_end_date' => ['nullable', 'date', 'required_if:status,seasonal', 'after_or_equal:season_start_date'],
        ]);

        app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(function () use ($validated, $branchProduct) {
            $branchProduct = BranchProduct::query()->whereKey($branchProduct->id)->lockForUpdate()->firstOrFail();
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

            app(InventoryOutbox::class)->stockChanged(
                $branchProduct, 'actualizo', 'la configuracion de inventario del producto', 'configured',
            );
        }));

        return back()->with('success', 'Configuracion del producto actualizada correctamente.');
    }

    private function minimumStockRules(string $unit): array
    {
        if ($unit === 'kg') {
            return ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999.999'];
        }

        return ['required', 'integer', 'min:0', 'max:999'];
    }

    private function serializeBranchProductRow(BranchProduct $branchProduct): array
    {
        $branchProduct->loadMissing([
            'branch:id,name,slug',
            'product:id,name,search_aliases,image,category_id,sale_price,cost,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,piece_pricing_mode,box_pricing_mode,piece_margin_percentage,box_margin_percentage,active,updated_at,catalog_version',
            'product.category:id,product_department_id,name',
            'product.category.productDepartment:id,name',
            'product.barcodes:id,product_id,code',
        ]);
        if (! array_key_exists('active_batches_count', $branchProduct->getAttributes())) {
            $branchProduct->loadCount('activeBatches as active_batches_count');
        }
        if (! array_key_exists('next_expiration_date', $branchProduct->getAttributes())) {
            $branchProduct->loadMin('activeBatches as next_expiration_date', 'expiration_date');
        }

        return [
            'id' => $branchProduct->id,
            'branch_id' => $branchProduct->branch_id,
            'product_id' => $branchProduct->product_id,
            'barcode' => $branchProduct->barcode,
            'stock' => $branchProduct->stock,
            'min_stock' => $branchProduct->min_stock,
            'status' => $branchProduct->status,
            'season_start_date' => $branchProduct->season_start_date,
            'season_end_date' => $branchProduct->season_end_date,
            'last_restocked_at' => $branchProduct->last_restocked_at,
            'inactive_candidate_after_days' => $branchProduct->inactive_candidate_after_days,
            'tracks_batches' => $branchProduct->tracks_batches,
            'tracks_expiration' => $branchProduct->tracks_expiration,
            'active_batches_count' => $branchProduct->active_batches_count ?? 0,
            'next_expiration_date' => $branchProduct->next_expiration_date,
            'branch' => $branchProduct->branch,
            'product' => $branchProduct->product,
        ];
    }

    private function serializeBranchProductDetails(BranchProduct $branchProduct, Request $request): array
    {
        $mayReadHistory = $request->user()->hasPermission('inventory.branches.view');
        abort_if($request->boolean('include_movements') && ! $mayReadHistory, 403);
        $request->validate(['include_movements' => ['sometimes', 'boolean']]);
        $branchProduct->load([
            'branch:id,name,slug',
            'product:id,name,search_aliases,image,category_id,sale_price,cost,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box,piece_pricing_mode,box_pricing_mode,piece_margin_percentage,box_margin_percentage,active,updated_at,catalog_version',
            'product.category:id,product_department_id,name',
            'product.category.productDepartment:id,name',
            'product.barcodes:id,product_id,code',
            'batches' => fn ($query) => $query
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
                ->orderByRaw('expiration_date IS NULL')
                ->orderBy('expiration_date')
                ->orderBy('id'),
        ]);

        $payload = $branchProduct->toArray();
        $payload['assigned_branch_ids'] = BranchProduct::query()
            ->where('product_id', $branchProduct->product_id)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return $mayReadHistory && $request->boolean('include_movements', true)
            ? array_merge($payload, app(InventoryReadService::class)->movements($branchProduct, $request))
            : $payload;
    }

    private function inventoryBaseStatsQuery(?Branch $branch)
    {
        return BranchProduct::query()
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch->id));
    }

    private function inventoryBatchAlertsQuery(?Branch $branch)
    {
        return ProductBatch::query()
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->where('quantity', '>', 0)
            ->whereHas('branchProduct', function ($query) use ($branch) {
                $query->where('status', BranchProduct::STATUS_ACTIVE)
                    ->when($branch, fn ($innerQuery) => $innerQuery->where('branch_id', $branch->id));
            });
    }

    private function rotationExpiredSql(): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return "datetime(branch_products.last_restocked_at, branch_products.inactive_candidate_after_days || ' days') <= CURRENT_TIMESTAMP";
        }

        return 'DATE_ADD(branch_products.last_restocked_at, INTERVAL branch_products.inactive_candidate_after_days DAY) <= NOW()';
    }

    private function inventoryInactiveCandidatesQuery(?Branch $branch)
    {
        return $this->inventoryBaseStatsQuery($branch)
            ->whereNotNull('last_restocked_at')
            ->whereRaw($this->rotationExpiredSql());
    }

    private function inventorySummaryPayload(?Branch $branch, bool $includeAlerts = true): array
    {
        $today = now()->toDateString();
        $nearExpirationLimit = now()->addDays(30)->toDateString();
        $inventory = $this->inventoryBaseStatsQuery($branch)
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->selectRaw('COUNT(*) as total_products')
            ->selectRaw('COALESCE(SUM(branch_products.stock), 0) as total_stock')
            ->selectRaw('SUM(CASE WHEN branch_products.stock <= branch_products.min_stock AND branch_products.stock > 0 THEN 1 ELSE 0 END) as low_stock')
            ->selectRaw('SUM(CASE WHEN branch_products.stock <= 0 THEN 1 ELSE 0 END) as out_of_stock')
            ->selectRaw('SUM(CASE WHEN '.$this->rotationExpiredSql().' THEN 1 ELSE 0 END) as inactive_candidates')
            ->selectRaw('COALESCE(SUM(branch_products.stock * COALESCE(products.sale_price_per_piece, products.sale_price)), 0) as inventory_value')
            ->first();
        $batches = $this->inventoryBatchAlertsQuery($branch)
            ->selectRaw('SUM(CASE WHEN expiration_date < ? THEN 1 ELSE 0 END) as expired_batches', [$today])
            ->selectRaw('SUM(CASE WHEN expiration_date >= ? AND expiration_date <= ? THEN 1 ELSE 0 END) as near_expiration_batches', [$today, $nearExpirationLimit])
            ->first();

        $stats = [
            'total_products' => (int) ($inventory->total_products ?? 0),
            'total_stock' => (float) ($inventory->total_stock ?? 0),
            'low_stock' => (int) ($inventory->low_stock ?? 0),
            'out_of_stock' => (int) ($inventory->out_of_stock ?? 0),
            'expiring_soon' => (int) ($batches->near_expiration_batches ?? 0),
            'inactive_candidates' => (int) ($inventory->inactive_candidates ?? 0),
            'inventory_value' => (float) ($inventory->inventory_value ?? 0),
        ];
        $alerts = [
            'expired_batches' => (int) ($batches->expired_batches ?? 0),
            'near_expiration_batches' => $stats['expiring_soon'],
            'low_stock_products' => $stats['low_stock'],
            'inactive_candidate_products' => $stats['inactive_candidates'],
        ];

        return [
            'inventoryStats' => $stats,
            'inventoryAlerts' => $includeAlerts ? array_merge($alerts, $this->inventoryAlertsPayload($branch)) : $alerts,
        ];
    }

    private function inventoryAlertsPayload(?Branch $branch): array
    {
        $today = now()->toDateString();
        $nearExpirationLimit = now()->addDays(30)->toDateString();
        $baseStatsQuery = $this->inventoryBaseStatsQuery($branch);
        $batchAlertsQuery = $this->inventoryBatchAlertsQuery($branch);
        $inactiveCandidateProductsQuery = $this->inventoryInactiveCandidatesQuery($branch);

        $expiredBatchesList = (clone $batchAlertsQuery)
            ->with(['branchProduct.product.barcodes'])
            ->whereDate('expiration_date', '<', $today)
            ->orderBy('expiration_date')
            ->limit(50)
            ->get()
            ->map(fn ($batch) => $this->mapBatchAlert($batch))
            ->values();

        $nearExpirationBatchesList = (clone $batchAlertsQuery)
            ->with(['branchProduct.product.barcodes'])
            ->whereDate('expiration_date', '>=', $today)
            ->whereDate('expiration_date', '<=', $nearExpirationLimit)
            ->orderBy('expiration_date')
            ->limit(50)
            ->get()
            ->map(fn ($batch) => $this->mapBatchAlert($batch))
            ->values();

        $lowStockProductsList = (clone $baseStatsQuery)
            ->with(['product.barcodes'])
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0)
            ->orderBy('stock')
            ->limit(50)
            ->get()
            ->map(fn ($branchProduct) => $this->mapLowStockProduct($branchProduct))
            ->values();

        $inactiveCandidateProductsList = (clone $inactiveCandidateProductsQuery)
            ->with(['product.barcodes'])
            ->orderBy('last_restocked_at')
            ->limit(50)
            ->get()
            ->map(fn ($branchProduct) => $this->mapInactiveCandidateProduct($branchProduct))
            ->values();

        return [
            'expired_batches_list' => $expiredBatchesList,
            'near_expiration_batches_list' => $nearExpirationBatchesList,
            'low_stock_products_list' => $lowStockProductsList,
            'inactive_candidate_products_list' => $inactiveCandidateProductsList,
        ];
    }

    private function mapBatchAlert(ProductBatch $batch): array
    {
        $branchProduct = $batch->branchProduct;
        $product = $branchProduct?->product;

        $productName = collect([
            $product?->name,
            $product?->barcodes?->first()?->code,
        ])->first(fn ($value) => filled($value));

        $productCode = collect([
            $product?->barcodes?->first()?->code,
            $product?->barcodes?->first()?->code,
            "BP-{$batch->branch_product_id}",
        ])->first(fn ($value) => filled($value));

        return [
            'id' => $batch->id,
            'branch_product_id' => $batch->branch_product_id,
            'lot_number' => $batch->lot_number,
            'expiration_date' => $batch->expiration_date,
            'formatted_expiration_date' => $batch->formatted_expiration_date,
            'quantity' => $batch->quantity,
            'unit' => $product?->inventory_quantity_mode === 'legacy_presentation'
                ? ($product?->unit ?? 'piezas')
                : ($product?->inventory_unit ?? $product?->unit ?? 'piezas'),
            'initial_quantity' => $batch->initial_quantity,
            'status' => $batch->status,
            'expiration_status' => $batch->expiration_status,
            'expiration_human_text' => $batch->expiration_human_text,
            'product_name' => $productName ?: 'Producto sin nombre',
            'product_code' => $productCode,
            'branch_product' => $branchProduct,
        ];
    }

    private function mapLowStockProduct(BranchProduct $branchProduct): array
    {
        $product = $branchProduct->product;

        $productName = collect([
            $product?->name,
            $product?->barcodes?->first()?->code,
        ])->first(fn ($value) => filled($value));

        $productCode = collect([
            $product?->barcodes?->first()?->code,
            $product?->barcodes?->first()?->code,
            "BP-{$branchProduct->id}",
        ])->first(fn ($value) => filled($value));

        return [
            'id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'name' => $productName ?: 'Producto sin nombre',
            'code' => $productCode,
            'stock' => $branchProduct->stock,
            'unit' => $product?->inventory_quantity_mode === 'legacy_presentation'
                ? ($product?->unit ?? 'piezas')
                : ($product?->inventory_unit ?? $product?->unit ?? 'piezas'),
            'minStock' => $branchProduct->min_stock,
            'min_stock' => $branchProduct->min_stock,
            'status' => 'Stock bajo',
            'product' => $product,
            'branch_product' => $branchProduct,
        ];
    }

    private function mapInactiveCandidateProduct(BranchProduct $branchProduct): array
    {
        return array_merge($this->mapLowStockProduct($branchProduct), [
            'status' => 'Producto sin rotacion',
            'last_restocked_at' => $branchProduct->last_restocked_at,
            'inactive_candidate_after_days' => $branchProduct->inactive_candidate_after_days,
        ]);
    }
}
