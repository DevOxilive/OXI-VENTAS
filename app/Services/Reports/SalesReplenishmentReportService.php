<?php

namespace App\Services\Reports;

use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReplenishmentReportService
{
    public function __construct(
        private readonly ProductSearchService $productSearch,
    ) {}

    public function build(Collection $branches, array $filters): array
    {
        $branchIds = collect($filters['branch_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();

        if ($branchIds->isEmpty()) {
            return $this->emptyReport($branches);
        }

        $sales = $this->salesByProductAndBranch($branchIds, $filters);
        $stocks = $this->stockByProductAndBranch($branchIds);
        $products = $this->products($branchIds, $sales, $stocks, $filters);
        $rows = $products
            ->map(fn ($product) => $this->mapProductRow($product, $branches, $sales, $stocks, $filters))
            ->values();

        return [
            'branches' => $branches->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
            ])->values()->all(),
            'date_to' => $this->periodEnd($filters)->toDateString(),
            'sections' => [
                'pedido' => $this->sectionRows($rows, 'pedido'),
                'transferencias' => $this->sectionRows($rows, 'transferencias'),
                'sin-movimiento' => $this->sectionRows($rows, 'sin-movimiento'),
                'pedido-tiendas' => $this->sectionRows($rows, 'pedido-tiendas'),
            ],
            'summary' => [
                'products' => $rows->count(),
                'pedido' => $rows->where('flags.needs_order', true)->count(),
                'transferencias' => $rows->where('flags.has_transfer_opportunity', true)->count(),
                'sin_movimiento' => $rows->where('flags.no_movement', true)->count(),
                'pedido_tiendas' => $rows->count(),
            ],
        ];
    }

    private function products(Collection $branchIds, Collection $sales, Collection $stocks, array $filters): Collection
    {
        $productIds = $sales->pluck('product_id')
            ->merge($stocks->pluck('product_id'))
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $query = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_departments', 'product_departments.id', '=', 'categories.product_department_id')
            ->whereIn('products.id', $productIds)
            ->where('products.active', true)
            ->whereNull('products.deleted_at')
            ->select([
                'products.id',
                'products.name',
                'products.category_id',
                'products.inventory_unit',
                'products.unit',
                'products.pieces_per_box',
                'products.sale_price',
                'products.cost',
                'products.sale_price_per_piece',
                'products.cost_per_piece',
                'products.sale_price_per_box',
                'products.cost_per_box',
                'categories.name as category',
                'categories.product_department_id',
                'product_departments.name as department',
                DB::raw("(
                    SELECT barcodes.code
                    FROM barcodes
                    WHERE barcodes.product_id = products.id
                        AND barcodes.deleted_at IS NULL
                    ORDER BY barcodes.id ASC
                    LIMIT 1
                ) as code"),
            ])
            ->orderBy('product_departments.sort_order')
            ->orderBy('product_departments.name')
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->orderBy('products.name');

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $this->productSearch->constrain(
                $query,
                $search,
                'products.id',
                new ProductSearchOptions(
                    onlyActiveProducts: true,
                    limit: (int) config('product_search.max_results', 10000),
                ),
            );
        }

        $departmentIds = collect($filters['department_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($departmentIds->isNotEmpty()) {
            $query->whereIn('categories.product_department_id', $departmentIds);
        }

        $categoryIds = collect($filters['category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($categoryIds->isNotEmpty()) {
            $query->whereIn('products.category_id', $categoryIds);
        }

        $filteredProductIds = collect($filters['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values();
        if ($filteredProductIds->isNotEmpty()) {
            $query->whereIn('products.id', $filteredProductIds);
        }

        return $query->get();
    }

    private function salesByProductAndBranch(Collection $branchIds, array $filters): Collection
    {
        $query = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->select([
                'sale_details.product_id',
                'sales.branch_id',
                DB::raw('DATE(sales.date) as sale_date'),
                DB::raw('SUM(COALESCE(sale_details.base_quantity, sale_details.quantity)) as sold_quantity'),
                DB::raw('MAX(sales.date) as last_sale_at'),
            ])
            ->groupBy('sale_details.product_id', 'sales.branch_id', DB::raw('DATE(sales.date)'));

        $query->whereDate('sales.date', '>=', $this->minimumPeriodStart($filters)->toDateString());

        $query->whereDate('sales.date', '<=', $this->periodEnd($filters)->toDateString());

        return $query->get();
    }

    private function stockByProductAndBranch(Collection $branchIds): Collection
    {
        return DB::table('branch_products')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select([
                'product_id',
                'branch_id',
                DB::raw('SUM(stock) as stock'),
                DB::raw('SUM(min_stock) as min_stock'),
            ])
            ->groupBy('product_id', 'branch_id')
            ->get();
    }

    private function mapProductRow($product, Collection $branches, Collection $sales, Collection $stocks, array $filters): array
    {
        $unit = $this->baseUnit($product->inventory_unit ?? $product->unit ?? 'pza');
        $sectionKey = $this->sectionKey($product);
        $period = $this->periodForSection($filters, $sectionKey);
        $monthsInPeriod = $this->monthsBetween($period['from'], $period['to']);
        $productSales = $sales
            ->where('product_id', $product->id)
            ->filter(fn ($sale) => Carbon::parse($sale->sale_date)->betweenIncluded($period['from'], $period['to']));
        $productStocks = $stocks->where('product_id', $product->id);
        $branchMetrics = [];
        $totalSold = 0.0;
        $totalStock = 0.0;
        $totalInitialShortage = 0.0;
        $totalExcess = 0.0;

        foreach ($branches as $branch) {
            $sold = (float) $productSales
                ->where('branch_id', $branch->id)
                ->sum('sold_quantity');
            $stock = (float) ($productStocks->firstWhere('branch_id', $branch->id)->stock ?? 0);
            $monthlyAverage = $sold / $monthsInPeriod;
            $target = $monthlyAverage;
            $suggested = max(0, $target - $stock);
            $excess = max(0, $stock - $target);

            $totalSold += $sold;
            $totalStock += $stock;
            $totalInitialShortage += $suggested;
            $totalExcess += $excess;

            $branchMetrics[] = [
                'branch_id' => (int) $branch->id,
                'branch' => $branch->name,
                'raw_stock' => $stock,
                'raw_monthly_sales' => $monthlyAverage,
                'raw_suggested' => $suggested,
                'raw_excess' => $excess,
                'stock' => $this->normalizeQuantity($stock, $unit),
                'sold' => $this->normalizeQuantity($sold, $unit),
                'monthly_sales' => $this->normalizeQuantity($monthlyAverage, $unit),
                'suggested' => $this->normalizeQuantity($suggested, $unit),
                'excess' => $this->normalizeQuantity($excess, $unit),
                'transfer_in' => 0,
                'transfer_out' => 0,
                'stock_status' => $stock >= $target ? 'covered' : 'shortage',
            ];
        }

        $transferPlan = $this->transferPlan($branchMetrics, $unit);
        $branchMetrics = $this->applyTransferPlanToBranches($branchMetrics, $transferPlan, $unit);
        $redistributed = collect($transferPlan)->sum('quantity');
        $totalSuggested = max(0, $totalInitialShortage - $redistributed);
        $noMovement = $totalStock > 0 && $totalSold <= 0;

        return [
            'id' => (int) $product->id,
            'code' => $product->code ?: '-',
            'package_factor' => (int) ($product->pieces_per_box ?: 1),
            'unit' => $unit,
            'total_sold' => $this->normalizeQuantity($totalSold, $unit),
            'total_stock' => $this->normalizeQuantity($totalStock, $unit),
            'total_suggested' => $this->normalizeQuantity($totalSuggested, $unit),
            'transferable_stock' => $this->normalizeQuantity($redistributed, $unit),
            'section_key' => $sectionKey,
            'section' => $this->sectionLabel($product->department, $product->category, $period['label']),
            'period_from' => $period['from']->toDateString(),
            'period_to' => $period['to']->toDateString(),
            'period_label' => $period['label'],
            'department_id' => $product->product_department_id,
            'category_id' => $product->category_id,
            'department' => $product->department ?: 'Sin departamento',
            'category' => $product->category ?: 'Sin categoria',
            'product' => $product->name,
            'branches' => $branchMetrics,
            'sale_price' => (float) ($product->sale_price_per_piece ?? $product->sale_price ?? 0),
            'cost' => (float) ($product->cost_per_piece ?? $product->cost ?? 0),
            'transfers' => $transferPlan,
            'observation' => $this->observation($totalStock, $totalSold, $totalSuggested, $transferPlan, $unit),
            'flags' => [
                'needs_order' => $totalSuggested > 0,
                'has_transfer_opportunity' => count($transferPlan) > 0,
                'no_movement' => $noMovement,
                'store_order_candidate' => $totalSuggested > 0 && ! $noMovement,
            ],
        ];
    }

    private function sectionRows(Collection $rows, string $section): array
    {
        $filtered = match ($section) {
            'transferencias' => $rows->where('flags.has_transfer_opportunity', true),
            'sin-movimiento' => $rows->where('flags.no_movement', true),
            'pedido-tiendas' => $rows,
            default => $rows,
        };

        return $filtered
            ->groupBy('section')
            ->map(fn ($items, $label) => [
                'label' => $label,
                'key' => $items->first()['section_key'] ?? $label,
                'period_from' => $items->first()['period_from'] ?? null,
                'period_to' => $items->first()['period_to'] ?? null,
                'period_label' => $items->first()['period_label'] ?? null,
                'rows' => $items->values()->all(),
            ])
            ->values()
            ->all();
    }

    private function transferPlan(array $branchMetrics, string $unit): array
    {
        $donors = collect($branchMetrics)
            ->filter(fn ($branch) => $branch['raw_excess'] > 0)
            ->sortByDesc('raw_excess')
            ->map(fn ($branch) => [
                'branch_id' => $branch['branch_id'],
                'branch' => $branch['branch'],
                'available' => $branch['raw_excess'],
            ])
            ->values();

        $receivers = collect($branchMetrics)
            ->filter(fn ($branch) => $branch['raw_suggested'] > 0)
            ->sortByDesc('raw_suggested')
            ->map(fn ($branch) => [
                'branch_id' => $branch['branch_id'],
                'branch' => $branch['branch'],
                'needed' => $branch['raw_suggested'],
            ])
            ->values();

        $transfers = [];

        foreach ($receivers as $receiverIndex => $receiver) {
            $pending = (float) $receiver['needed'];

            foreach ($donors as $donorIndex => $donor) {
                if ($pending <= 0) {
                    break;
                }

                if ((float) $donor['available'] <= 0) {
                    continue;
                }

                $quantity = min((float) $donor['available'], $pending);
                $normalizedQuantity = $this->normalizeQuantity($quantity, $unit);

                if ($normalizedQuantity <= 0) {
                    continue;
                }

                $transfers[] = [
                    'from_branch_id' => (int) $donor['branch_id'],
                    'from_branch' => $donor['branch'],
                    'to_branch_id' => (int) $receiver['branch_id'],
                    'to_branch' => $receiver['branch'],
                    'quantity' => $normalizedQuantity,
                    'unit' => $unit,
                ];

                $donors[$donorIndex] = [
                    ...$donor,
                    'available' => (float) $donor['available'] - $quantity,
                ];
                $pending -= $quantity;
            }

            $receivers[$receiverIndex] = [
                ...$receiver,
                'needed' => $pending,
            ];
        }

        return $transfers;
    }

    private function applyTransferPlanToBranches(array $branchMetrics, array $transfers, string $unit): array
    {
        foreach ($branchMetrics as $index => $branch) {
            $transferIn = collect($transfers)
                ->where('to_branch_id', $branch['branch_id'])
                ->sum('quantity');
            $transferOut = collect($transfers)
                ->where('from_branch_id', $branch['branch_id'])
                ->sum('quantity');

            $branchMetrics[$index]['transfer_in'] = $this->normalizeQuantity((float) $transferIn, $unit);
            $branchMetrics[$index]['transfer_out'] = $this->normalizeQuantity((float) $transferOut, $unit);
        }

        return $branchMetrics;
    }

    private function observation(float $stock, float $sold, float $suggested, array $transfers, string $unit): string
    {
        if ($stock > 0 && $sold <= 0) {
            return 'Sin movimiento en el periodo.';
        }

        if (count($transfers) > 0) {
            return collect($transfers)
                ->map(fn ($transfer) => $this->quantityWithUnit((float) $transfer['quantity'], $unit).' de '.$transfer['from_branch'].' a '.$transfer['to_branch'])
                ->implode(', ');
        }

        if ($suggested > 0) {
            return 'Pedido sugerido por rotacion.';
        }

        return 'Stock suficiente para el periodo.';
    }

    private function sectionLabel(?string $department, ?string $category, string $periodLabel): string
    {
        return trim(($department ?: 'Sin departamento').' / '.($category ?: 'Sin categoria').' '.$periodLabel);
    }

    private function periodForSection(array $filters, string $sectionKey): array
    {
        $periods = $filters['section_periods'] ?? [];
        $from = $periods[$sectionKey] ?? ($filters['date_from'] ?? now()->subMonths(2)->toDateString());
        $to = $this->periodEnd($filters);
        $fromDate = Carbon::parse($from)->startOfDay();

        if ($fromDate->greaterThan($to)) {
            $fromDate = $to->copy()->startOfDay();
        }

        return [
            'from' => $fromDate,
            'to' => $to,
            'label' => $this->periodLabel($fromDate, $to),
        ];
    }

    private function minimumPeriodStart(array $filters): Carbon
    {
        $dates = collect($filters['section_periods'] ?? [])
            ->filter()
            ->push($filters['date_from'] ?? now()->subMonths(2)->toDateString())
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        return $dates->min() ?: now()->subMonths(2)->startOfDay();
    }

    private function periodEnd(array $filters): Carbon
    {
        return Carbon::parse($filters['date_to'] ?? now())->endOfDay();
    }

    private function monthsBetween(Carbon $from, Carbon $to): float
    {
        $diff = $from->startOfDay()->diff($to->startOfDay());
        $months = ($diff->y * 12) + $diff->m + ($diff->d / 30);

        return max(1, round($months, 3));
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        $diff = $from->diff($to);
        $parts = [];

        if ($diff->m + ($diff->y * 12) > 0) {
            $months = $diff->m + ($diff->y * 12);
            $parts[] = $months.' '.($months === 1 ? 'mes' : 'meses');
        }

        if ($diff->d > 0) {
            $parts[] = $diff->d.' '.($diff->d === 1 ? 'dia' : 'dias');
        }

        return $parts ? implode(' ', $parts) : 'hoy';
    }

    private function sectionKey($product): string
    {
        return ((int) ($product->product_department_id ?? 0)).'-'.((int) ($product->category_id ?? 0));
    }

    private function normalizeQuantity(float $quantity, string $unit): float
    {
        return $unit === 'kg'
            ? round($quantity, 3)
            : (float) round($quantity);
    }

    private function quantityWithUnit(float $quantity, string $unit): string
    {
        $normalized = $this->normalizeQuantity($quantity, $unit);
        $formatted = $unit === 'kg'
            ? rtrim(rtrim(number_format($normalized, 3, '.', ''), '0'), '.')
            : (string) (int) round($normalized);

        return $formatted.' '.$unit;
    }

    private function baseUnit(string $unit): string
    {
        return strtolower($unit) === 'kg' ? 'kg' : 'pza';
    }

    private function emptyReport(Collection $branches): array
    {
        return [
            'branches' => $branches->values()->all(),
            'coverage_months' => 2,
            'period_months' => 1,
            'sections' => [
                'pedido' => [],
                'transferencias' => [],
                'sin-movimiento' => [],
                'pedido-tiendas' => [],
            ],
            'summary' => [
                'products' => 0,
                'pedido' => 0,
                'transferencias' => 0,
                'sin_movimiento' => 0,
                'pedido_tiendas' => 0,
            ],
        ];
    }
}
