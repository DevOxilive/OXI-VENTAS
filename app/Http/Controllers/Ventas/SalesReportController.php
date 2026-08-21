<?php

namespace App\Http\Controllers\Ventas;

use App\Exports\SalesReportExport;
use App\Exports\SalesReplenishmentReportExport;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Category;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\ProductDepartment;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\Reports\SalesReplenishmentReportService;
use App\Support\TablePagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class SalesReportController extends Controller
{
    use AuthorizesBranchAccess;

    private const TAB_PRODUCTS = 'products';
    private const TAB_SALES = 'sales';
    private const TAB_ORDER = 'pedido';
    private const TAB_TRANSFERS = 'transferencias';
    private const TAB_NO_MOVEMENT = 'sin-movimiento';
    private const TAB_STORE_ORDER = 'pedido-tiendas';

    private const REPLENISHMENT_TABS = [
        self::TAB_ORDER,
        self::TAB_TRANSFERS,
        self::TAB_NO_MOVEMENT,
        self::TAB_STORE_ORDER,
    ];

    public function global(Request $request)
    {
        return $this->renderReport($request);
    }

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->renderReport($request, $branch);
    }

    public function exportGlobalProductsExcel(Request $request)
    {
        return $this->downloadProductsExcel($request);
    }

    public function exportProductsExcel(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->downloadProductsExcel($request, $branch);
    }

    public function exportGlobalSalesExcel(Request $request)
    {
        return $this->downloadSalesExcel($request);
    }

    public function exportSalesExcel(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->downloadSalesExcel($request, $branch);
    }

    public function exportGlobalSalesPdf(Request $request)
    {
        return $this->downloadSalesPdf($request);
    }

    public function exportSalesPdf(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->downloadSalesPdf($request, $branch);
    }

    private function renderReport(Request $request, ?Branch $branch = null)
    {
        $branches = $this->reportBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'reportes de ventas');
        }

        $filters = $this->resolveFilters($request, $branch, $branches);
        $activeTab = $filters['tab'];
        $reportBranches = $this->selectedReportBranches($branch, $branches, $filters);
        $replenishmentReport = app(SalesReplenishmentReportService::class)->build($reportBranches, $filters);

        return Inertia::render('Inventory/Reports/SalesReports', [
            'currentBranch' => $branch ? $this->mapBranch($branch) : null,
            'reportScope' => $branch ? 'branch' : 'global',
            'branchesDB' => $branches->map(fn (Branch $availableBranch) => $this->mapBranch($availableBranch))->values(),
            'selectionCatalogs' => $this->selectionCatalogs($branches),
            'filters' => $this->mapFilters($filters),
            'activeTab' => $activeTab,
            'salesReplenishment' => $replenishmentReport,
            'productsSold' => $activeTab === self::TAB_PRODUCTS
                ? $this->productsSoldQuery($filters)
                    ->paginate((int) $filters['per_page'])
                    ->withQueryString()
                    ->through(fn ($row) => $this->mapProductSoldRow($row, $filters))
                : $this->emptyPaginator(),
            'registeredSales' => $activeTab === self::TAB_SALES
                ? $this->registeredSalesQuery($filters)
                    ->paginate((int) $filters['per_page'])
                    ->withQueryString()
                    ->through(fn (Sale $sale) => $this->mapSaleRow($sale))
                : $this->emptyPaginator(),
        ]);
    }

    private function downloadProductsExcel(Request $request, ?Branch $branch = null)
    {
        $branches = $this->reportBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'reportes de ventas');
        }

        $filters = $this->resolveFilters($request, $branch, $branches);
        $fileScope = $branch ? $this->safeSegment($branch->slug ?: $branch->name) : 'global';

        if (in_array($filters['tab'], self::REPLENISHMENT_TABS, true)) {
            $reportBranches = $this->selectedReportBranches($branch, $branches, $filters);
            $report = app(SalesReplenishmentReportService::class)->build($reportBranches, $filters);

            return Excel::download(
                new SalesReplenishmentReportExport($report, $filters['tab']),
                'reporte-ventas-'.$filters['tab'].'-'.$fileScope.'-'.now()->format('Y-m-d-H-i').'.xlsx'
            );
        }

        $rows = $this->productsSoldQuery($filters)
            ->get()
            ->map(fn ($row) => $this->mapProductSoldRow($row, $filters));

        return Excel::download(
            new SalesReportExport($rows, 'Productos vendidos', 'products'),
            'reporte-productos-vendidos-'.$fileScope.'-'.now()->format('Y-m-d-H-i').'.xlsx'
        );
    }

    private function downloadSalesExcel(Request $request, ?Branch $branch = null)
    {
        $branches = $this->reportBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'reportes de ventas');
        }

        $filters = $this->resolveFilters($request, $branch, $branches);
        $rows = $this->registeredSalesQuery($filters)
            ->get()
            ->map(fn (Sale $sale) => $this->mapSaleRow($sale));
        $fileScope = $branch ? $this->safeSegment($branch->slug ?: $branch->name) : 'global';

        return Excel::download(
            new SalesReportExport($rows, 'Ventas registradas', 'sales'),
            'reporte-ventas-registradas-'.$fileScope.'-'.now()->format('Y-m-d-H-i').'.xlsx'
        );
    }

    private function downloadSalesPdf(Request $request, ?Branch $branch = null)
    {
        $branches = $this->reportBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'reportes de ventas');
        }

        $filters = $this->resolveFilters($request, $branch, $branches);
        $rows = $this->registeredSalesQuery($filters)
            ->get()
            ->map(fn (Sale $sale) => $this->mapSaleRow($sale));
        $fileScope = $branch ? $this->safeSegment($branch->slug ?: $branch->name) : 'global';

        $pdf = Pdf::loadView('pdf.sales-registered-report', [
            'branch' => $branch ?: (object) ['name' => 'Todas las sucursales accesibles'],
            'filters' => $filters,
            'rows' => $rows,
            'title' => 'Ventas registradas',
        ])->setPaper('letter', 'landscape');

        return $pdf->download('reporte-ventas-registradas-'.$fileScope.'-'.now()->format('Y-m-d-H-i').'.pdf');
    }

    public function exportSaleExcel(Request $request, Sale $sale)
    {
        $this->abortIfUserCannotAccessBranch($request, $sale->branch);

        $sale->loadMissing([
            'branch:id,name,slug',
            'employee:id,first_name,last_name',
            'details.product.barcodes:id,product_id,code',
        ]);

        return Excel::download(
            new SalesReportExport(collect([$this->mapSaleRow($sale)]), 'Detalle de venta', 'sale-detail'),
            'venta-'.$this->safeFolio($sale).'.xlsx'
        );
    }

    public function exportSalePdf(Request $request, Sale $sale)
    {
        $this->abortIfUserCannotAccessBranch($request, $sale->branch);

        $sale->loadMissing([
            'branch:id,name,slug',
            'employee:id,first_name,last_name',
            'details.product.barcodes:id,product_id,code',
        ]);

        $pdf = Pdf::loadView('pdf.sale-detail-report', [
            'sale' => $this->mapSaleRow($sale),
        ])->setPaper('letter', 'portrait');

        return $pdf->download('venta-'.$this->safeFolio($sale).'.pdf');
    }

    private function productsSoldQuery(array $filters)
    {
        $baseQuantity = 'COALESCE(sale_details.base_quantity, sale_details.quantity)';
        $primaryCodeSubquery = $this->primaryCodeSubquery('products.id');

        $query = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('branches', 'branches.id', '=', 'sales.branch_id')
            ->leftJoin('branch_products', function ($join) {
                $join
                    ->on('branch_products.product_id', '=', 'sale_details.product_id')
                    ->on('branch_products.branch_id', '=', 'sales.branch_id')
                    ->whereNull('branch_products.deleted_at');
            })
            ->leftJoin('barcodes as sale_barcodes', 'sale_barcodes.id', '=', 'sale_details.barcode_id')
            ->whereIn('sales.branch_id', $filters['branch_ids'])
            ->where('sales.status', 'completed')
            ->select([
                DB::raw('MIN(sale_details.id) as id'),
                'products.id as product_id',
                'products.name as product',
                'products.inventory_unit',
                'products.unit',
                'branches.id as branch_id',
                'branches.name as branch',
                'categories.name as category',
                DB::raw('COALESCE(branch_products.stock, 0) as current_stock'),
                DB::raw("COALESCE(($primaryCodeSubquery), branch_products.barcode, MIN(sale_barcodes.code), '-') as code"),
                DB::raw("COALESCE(SUM($baseQuantity), 0) as sold_quantity"),
                DB::raw('MAX(sales.date) as last_sale_at'),
            ])
            ->groupBy([
                'products.id',
                'products.name',
                'products.inventory_unit',
                'products.unit',
                'branches.id',
                'branches.name',
                'categories.name',
                'branch_products.stock',
                'branch_products.barcode',
            ])
            ->orderByDesc('sold_quantity')
            ->orderBy('products.name');

        $this->applyDateFilters($query, $filters, 'sales.date');
        $this->applyProductSearch($query, $filters['search'] ?? '');

        return $query;
    }

    private function registeredSalesQuery(array $filters): Builder
    {
        $totalProductsSubquery = SaleDetail::query()
            ->selectRaw('COALESCE(SUM(COALESCE(base_quantity, quantity)), 0)')
            ->whereColumn('sale_details.sale_id', 'sales.id');

        $query = Sale::query()
            ->with([
                'branch:id,name,slug',
                'employee:id,first_name,last_name',
                'details.product.barcodes:id,product_id,code',
                'cancellation.cancelledBy:id,name',
                'cancellation.details.product:id,name',
            ])
            ->select('sales.*')
            ->selectSub($totalProductsSubquery, 'total_products_sold')
            ->whereIn('branch_id', $filters['branch_ids'])
            ->latest('date');

        $this->applyDateFilters($query, $filters, 'date');

        if (filled($filters['folio'] ?? null)) {
            $query->where('folio', 'like', '%'.$this->likeValue($filters['folio']).'%');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$this->likeValue($search).'%';

            $query->where(function (Builder $subQuery) use ($like) {
                $subQuery
                    ->where('folio', 'like', $like)
                    ->orWhereHas('employee', function (Builder $employeeQuery) use ($like) {
                        $employeeQuery
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    })
                    ->orWhereHas('details.product', function (Builder $productQuery) use ($like) {
                        $productQuery
                            ->where('name', 'like', $like)
                            ->orWhereHas('barcodes', fn (Builder $barcodeQuery) => $barcodeQuery->where('code', 'like', $like));
                    });
            });
        }

        return $query;
    }

    private function resolveFilters(Request $request, ?Branch $branch = null, $branches = null): array
    {
        $validated = $request->validate([
            'tab' => ['nullable', 'in:products,sales,pedido,transferencias,sin-movimiento,pedido-tiendas'],
            'search' => ['nullable', 'string', 'max:120'],
            'folio' => ['nullable', 'string', 'max:80'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'coverage_months' => ['nullable', 'integer', 'in:1,2,3,6'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
            'section_periods' => ['nullable', 'array'],
            'section_periods.*' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'in:10,25,50,100,200'],
        ]);
        $branches ??= $this->reportBranches($request);
        $accessibleBranchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->values();
        $requestedBranchIds = collect($validated['branch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->intersect($accessibleBranchIds)
            ->values();
        $branchIds = $branch
            ? [(int) $branch->id]
            : ($requestedBranchIds->isNotEmpty() ? $requestedBranchIds->all() : $accessibleBranchIds->all());

        $tab = $validated['tab'] ?? self::TAB_ORDER;
        $tab = $tab === self::TAB_PRODUCTS ? self::TAB_ORDER : $tab;

        return [
            'tab' => $tab,
            'search' => trim((string) ($validated['search'] ?? '')),
            'folio' => trim((string) ($validated['folio'] ?? '')),
            'date_from' => $validated['date_from'] ?? now()->startOfMonth()->toDateString(),
            'date_to' => $validated['date_to'] ?? now()->toDateString(),
            'coverage_months' => (int) ($validated['coverage_months'] ?? 2),
            'department_ids' => $this->integerFilterValues($validated['department_ids'] ?? []),
            'category_ids' => $this->integerFilterValues($validated['category_ids'] ?? []),
            'product_ids' => $this->integerFilterValues($validated['product_ids'] ?? []),
            'section_periods' => collect($validated['section_periods'] ?? [])
                ->filter(fn ($date) => filled($date))
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->all(),
            'branch_id' => $branch ? (int) $branch->id : null,
            'branch_ids' => $branchIds,
            'scope' => $branch ? 'branch' : 'global',
            'per_page' => TablePagination::resolvePerPage($request),
        ];
    }

    private function mapFilters(array $filters): array
    {
        return [
            'tab' => $filters['tab'],
            'search' => $filters['search'],
            'folio' => $filters['folio'],
            'date_from' => $filters['date_from'],
            'date_to' => $filters['date_to'],
            'coverage_months' => $filters['coverage_months'],
            'branch_ids' => $filters['branch_ids'],
            'department_ids' => $filters['department_ids'],
            'category_ids' => $filters['category_ids'],
            'product_ids' => $filters['product_ids'],
            'section_periods' => $filters['section_periods'],
            'branch_id' => $filters['branch_id'],
            'scope' => $filters['scope'],
            'per_page' => $filters['per_page'],
        ];
    }

    private function mapProductSoldRow($row, array $filters): array
    {
        $unit = $this->baseUnit($row->inventory_unit ?? $row->unit ?? 'pza');
        $soldQuantity = (float) ($row->sold_quantity ?? 0);
        $currentStock = (float) ($row->current_stock ?? 0);
        $monthlyAverage = $soldQuantity / $this->monthsInPeriod($filters);
        $monthlyAverageValue = $unit === 'kg'
            ? round($monthlyAverage, 3)
            : (float) round($monthlyAverage);

        return [
            'id' => 'product-'.$row->product_id.'-'.($row->branch_id ?? $filters['branch_id'] ?? 'global'),
            'product_id' => (int) $row->product_id,
            'product' => $row->product ?? 'Producto sin nombre',
            'code' => $row->code ?: '-',
            'branch' => $row->branch ?? '-',
            'category' => $row->category ?? '-',
            'inventory_unit' => $unit,
            'current_stock' => $currentStock,
            'current_stock_display' => $this->quantityLabel($currentStock, $unit),
            'sold_quantity' => $soldQuantity,
            'sold_quantity_display' => $this->quantityLabel($soldQuantity, $unit),
            'monthly_average' => $monthlyAverageValue,
            'monthly_average_display' => $this->quantityLabel($monthlyAverage, $unit),
            'last_sale' => $row->last_sale_at,
            'last_sale_display' => $row->last_sale_at ? Carbon::parse($row->last_sale_at)->format('d/m/Y H:i') : '-',
        ];
    }

    private function mapSaleRow(Sale $sale): array
    {
        $totalProductsSold = (float) ($sale->total_products_sold
            ?? $sale->details->sum(fn (SaleDetail $detail) => (float) ($detail->base_quantity ?? $detail->quantity)));

        return [
            'id' => (int) $sale->id,
            'folio' => $sale->folio ?: 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
            'date' => optional($sale->date)->toISOString(),
            'date_display' => optional($sale->date)->format('d/m/Y H:i') ?? '-',
            'branch' => $sale->branch?->name ?? '-',
            'seller' => $this->employeeName($sale),
            'status' => $sale->status,
            'status_label' => $sale->status === 'cancelled' ? 'Cancelada' : 'Completada',
            'status_tone' => $sale->status === 'cancelled' ? 'danger' : 'success',
            'cancelled_at' => optional($sale->cancelled_at)->toISOString(),
            'cancelled_at_display' => optional($sale->cancelled_at)->format('d/m/Y H:i') ?? null,
            'cancellation' => $sale->cancellation ? [
                'id' => (int) $sale->cancellation->id,
                'reason' => $sale->cancellation->reason,
                'amount' => (float) $sale->cancellation->amount,
                'cancelled_at' => optional($sale->cancellation->cancelled_at)->toISOString(),
                'cancelled_at_display' => optional($sale->cancellation->cancelled_at)->format('d/m/Y H:i') ?? '-',
                'cancelled_by' => $sale->cancellation->cancelledBy?->name ?? 'Sin usuario',
                'details' => $sale->cancellation->details
                    ->map(fn ($detail) => [
                        'id' => (int) $detail->id,
                        'product' => $detail->product?->name ?? 'Producto sin nombre',
                        'quantity' => (float) $detail->quantity,
                        'base_quantity' => (float) $detail->base_quantity,
                        'subtotal' => (float) $detail->subtotal,
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'total_products_sold' => $totalProductsSold,
            'total_products_sold_display' => $this->saleTotalQuantityDisplay($sale, $totalProductsSold),
            'total' => (float) $sale->total,
            'details' => $sale->details
                ->map(fn (SaleDetail $detail) => $this->mapSaleDetail($detail))
                ->values()
                ->all(),
        ];
    }

    private function mapSaleDetail(SaleDetail $detail): array
    {
        $product = $detail->product;
        $unit = $this->baseUnit($product?->inventory_unit ?? $product?->unit ?? 'pza');
        $saleUnit = $detail->sale_unit ?: ($unit === 'kg' ? 'kilo' : 'piece');
        $baseQuantity = (float) ($detail->base_quantity ?? $detail->quantity ?? 0);
        $quantity = (float) ($detail->quantity ?? $baseQuantity);
        $code = $product?->barcodes?->first()?->code ?? '-';

        return [
            'id' => (int) $detail->id,
            'product' => $product?->name ?? 'Producto sin nombre',
            'code' => $code,
            'presentation' => $this->presentationLabel($saleUnit, $unit),
            'quantity' => $quantity,
            'quantity_display' => $this->visualQuantityLabel($quantity, $saleUnit, $unit),
            'base_quantity' => $baseQuantity,
            'base_quantity_display' => $this->quantityLabel($baseQuantity, $unit),
            'unit_price' => (float) $detail->unit_price,
            'original_unit_price' => (float) ($detail->original_unit_price ?? $detail->unit_price),
            'discount_percentage' => (float) ($detail->discount_percentage ?? 0),
            'discount_amount' => (float) ($detail->discount_amount ?? 0),
            'subtotal' => (float) $detail->subtotal,
        ];
    }

    private function applyDateFilters($query, array $filters, string $column): void
    {
        if (filled($filters['date_from'] ?? null)) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (filled($filters['date_to'] ?? null)) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }
    }

    private function applyProductSearch($query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = '%'.$this->likeValue($search).'%';

        $query->where(function ($subQuery) use ($like) {
            $subQuery
                ->where('products.name', 'like', $like)
                ->orWhere('categories.name', 'like', $like)
                ->orWhere('branch_products.barcode', 'like', $like)
                ->orWhere('sale_barcodes.code', 'like', $like)
                ->orWhereExists(function ($barcodeQuery) use ($like) {
                    $barcodeQuery
                        ->selectRaw('1')
                        ->from('barcodes')
                        ->whereColumn('barcodes.product_id', 'products.id')
                        ->where('barcodes.code', 'like', $like)
                        ->whereNull('barcodes.deleted_at');
                });
        });
    }

    private function primaryCodeSubquery(string $productColumn): string
    {
        return "SELECT barcodes.code FROM barcodes WHERE barcodes.product_id = {$productColumn} AND barcodes.deleted_at IS NULL ORDER BY barcodes.id ASC LIMIT 1";
    }

    private function monthsInPeriod(array $filters): int
    {
        $from = Carbon::parse($filters['date_from'] ?? now()->startOfMonth())->startOfMonth();
        $to = Carbon::parse($filters['date_to'] ?? now())->startOfMonth();

        return max(1, (($to->year - $from->year) * 12) + ($to->month - $from->month) + 1);
    }

    private function reportBranches(Request $request)
    {
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesión.');

        $branches = $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name')
            ->get();

        return $branches;
    }

    private function selectionCatalogs($branches): array
    {
        $branchIds = $branches->pluck('id')->map(fn ($id) => (int) $id)->values();

        $products = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.category_id',
                'categories.product_department_id',
            ])
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->where('products.active', true)
            ->whereNull('products.deleted_at')
            ->whereNull('branch_products.deleted_at')
            ->distinct()
            ->orderBy('products.name')
            ->get();

        $categoryIds = $products->pluck('category_id')->filter()->unique()->values();
        $departmentIds = $products->pluck('product_department_id')->filter()->unique()->values();

        return [
            'branches' => $branches->map(fn (Branch $branch) => $this->mapBranch($branch))->values()->all(),
            'departments' => ProductDepartment::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values()
                ->all(),
            'categories' => Category::query()
                ->whereIn('id', $categoryIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'product_department_id'])
                ->values()
                ->all(),
            'products' => $products
                ->map(fn ($product) => [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'category_id' => (int) $product->category_id,
                    'product_department_id' => (int) $product->product_department_id,
                ])
                ->values()
                ->all(),
        ];
    }

    private function selectedReportBranches(?Branch $branch, $branches, array $filters)
    {
        if ($branch) {
            return collect([$branch]);
        }

        $selectedBranchIds = collect($filters['branch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($selectedBranchIds->isEmpty()) {
            return $branches;
        }

        return $branches
            ->filter(fn (Branch $availableBranch) => $selectedBranchIds->contains((int) $availableBranch->id))
            ->values();
    }

    private function integerFilterValues(array $values): array
    {
        return collect($values)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function emptyPaginator(): array
    {
        return [
            'data' => [],
            'links' => [],
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 25,
            'total' => 0,
        ];
    }

    private function mapBranch(Branch $branch): array
    {
        return [
            'id' => (int) $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'color' => $branch->color,
        ];
    }

    private function employeeName(Sale $sale): string
    {
        $name = trim(($sale->employee?->first_name ?? '').' '.($sale->employee?->last_name ?? ''));

        return $name !== '' ? $name : 'Sin vendedor';
    }

    private function presentationLabel(string $saleUnit, string $baseUnit): string
    {
        return match ($saleUnit) {
            'box' => 'Caja',
            'kilo' => 'Kilo',
            default => $baseUnit === 'kg' ? 'Kilo' : 'Pieza',
        };
    }

    private function visualQuantityLabel(float $quantity, string $saleUnit, string $baseUnit): string
    {
        if ($saleUnit === 'box') {
            return $this->formatQuantity($quantity, 'pza').' cajas';
        }

        return $this->quantityLabel($quantity, $baseUnit);
    }

    private function quantityLabel(float $quantity, string $unit): string
    {
        return $this->formatQuantity($quantity, $unit).' '.($unit === 'kg' ? 'kg' : 'pzas');
    }

    private function saleTotalQuantityDisplay(Sale $sale, float $total): string
    {
        $hasDecimalBaseQuantity = $sale->details->contains(function (SaleDetail $detail) {
            $product = $detail->product;
            $unit = $this->baseUnit($product?->inventory_unit ?? $product?->unit ?? 'pza');
            $baseQuantity = (float) ($detail->base_quantity ?? $detail->quantity ?? 0);

            return $unit === 'kg' && abs($baseQuantity - round($baseQuantity)) > 0.0000001;
        });

        return $this->formatQuantity($total, $hasDecimalBaseQuantity ? 'kg' : 'pza');
    }

    private function formatQuantity(float $quantity, string $unit = 'pza'): string
    {
        if ($this->baseUnit($unit) !== 'kg') {
            return (string) (int) round($quantity);
        }

        $formatted = number_format($quantity, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function baseUnit(string $unit): string
    {
        return strtolower($unit) === 'kg' ? 'kg' : 'pza';
    }

    private function likeValue(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function safeFolio(Sale $sale): string
    {
        return $this->safeSegment($sale->folio ?: (string) $sale->id);
    }

    private function safeSegment(string $value): string
    {
        return trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $value), '-') ?: 'reporte';
    }
}
