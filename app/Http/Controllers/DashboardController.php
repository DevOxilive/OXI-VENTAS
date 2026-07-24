<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing(['role', 'role.permissions', 'permissions', 'branches']);
        $accessibleBranches = $user->accessibleBranchesQuery()
            ->orderBy('branches.name')
            ->get(['branches.id', 'branches.name', 'branches.slug', 'branches.color']);

        if (!$user->hasPermission('dashboard.executive.view')) {
            return Inertia::render('RoleDashboard', $this->roleDashboardPayload($user, $accessibleBranches));
        }

        $accessibleBranchIds = $accessibleBranches
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $maxDate = $this->resolveDashboardMaxDate($accessibleBranchIds);
        $filters = $this->resolveFilters($request, $accessibleBranchIds, $maxDate);
        $branchIds = $filters['branch_id']
            ? collect([(int) $filters['branch_id']])
            : $accessibleBranchIds;
        $branchPerformance = $this->branchPerformance($branchIds, $filters['start'], $filters['end']);
        $lowRotationProducts = $this->lowRotationProducts(
            branchIds: $branchIds,
            end: $filters['end'],
            thresholdDays: $this->rotationThreshold($filters['period']),
        );
        $lowRotationByBranch = $this->lowRotationByBranch(
            branchIds: $branchIds,
            end: $filters['end'],
            thresholdDays: $this->rotationThreshold($filters['period']),
        );
        $summary = $this->summary(
            branchIds: $branchIds,
            start: $filters['start'],
            end: $filters['end'],
            previousStart: $filters['previous_start'],
            previousEnd: $filters['previous_end'],
            branchPerformance: $branchPerformance,
            lowRotationByBranch: $lowRotationByBranch,
        );

        return Inertia::render('Dashboard', [
            'filters' => [
                'period' => $filters['period'],
                'branch_id' => $filters['branch_id'],
                'date_from' => $filters['start']->toDateString(),
                'date_to' => $filters['end']->toDateString(),
                'max_date' => $filters['max_date']->toDateString(),
                'label' => $filters['label'],
                'branches' => $accessibleBranches->map(fn (Branch $branch) => [
                    'id' => (int) $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                ])->values(),
            ],
            'dashboardWidgets' => $this->dashboardWidgets($branchIds, $filters['start'], $filters['end']),
            'summary' => $summary,
            'salesTrend' => $this->salesTrend(
                branchIds: $branchIds,
                period: $filters['period'],
                start: $filters['start'],
                end: $filters['end'],
            ),
            'branchPerformance' => $branchPerformance,
            'expiringBatches' => $this->expiringBatches($branchIds, $filters['start'], $filters['end']),
            'lowRotationProducts' => $lowRotationProducts,
            'lowRotationByBranch' => $lowRotationByBranch,
            'purchaseSummary' => $this->purchaseSummary($branchIds, $filters['start'], $filters['end']),
            'customerBreakdown' => $this->customerBreakdown($branchIds, $filters['start'], $filters['end']),
            'discountSummary' => $this->discountSummary($branchIds, $filters['start'], $filters['end']),
            'shrinkageSummary' => $this->shrinkageSummary($branchIds, $filters['start'], $filters['end']),
            'inventoryCoverage' => $this->inventoryCoverage($branchIds, $filters['end']),
            'annualSummary' => $this->annualSummary($branchIds, $filters['end']->year),
        ]);
    }

    private function roleDashboardPayload($user, Collection $accessibleBranches): array
    {
        return [
            'dashboardUser' => [
                'name' => $user->name,
                'role' => $user->role?->name ?? 'Usuario',
            ],
            'assignedBranches' => $accessibleBranches
                ->map(fn (Branch $branch) => [
                    'id' => (int) $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'color' => $branch->color,
                ])
                ->values(),
        ];
    }

    private function resolveFilters(Request $request, Collection $accessibleBranchIds, Carbon $maxDate): array
    {
        $period = in_array($request->string('period')->value(), ['day', 'week', 'month', 'year'], true)
            ? $request->string('period')->value()
            : 'month';

        $requestedBranchId = $request->integer('branch_id');
        $branchId = $requestedBranchId && $accessibleBranchIds->contains($requestedBranchId)
            ? $requestedBranchId
            : null;

        $hasDateFrom = $request->filled('date_from');
        $hasDateTo = $request->filled('date_to');

        $start = $hasDateFrom
            ? Carbon::parse($request->string('date_from')->value())->startOfDay()
            : $maxDate->copy()->subYear()->addDay()->startOfDay();

        $end = $hasDateTo
            ? Carbon::parse($request->string('date_to')->value())->endOfDay()
            : $maxDate->copy()->endOfDay();

        $maxDateEnd = $maxDate->copy()->endOfDay();

        if ($end->gt($maxDateEnd)) {
            $requestedDays = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
            $end = $maxDateEnd;

            if ($hasDateFrom && $hasDateTo && $requestedDays > 0) {
                $start = $end->copy()->subDays($requestedDays)->startOfDay();
            }
        }

        if ($start->gt($maxDateEnd)) {
            $start = $maxDateEnd->copy()->startOfDay();
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $label = sprintf(
            '%s - %s',
            $start->translatedFormat('d M Y'),
            $end->translatedFormat('d M Y'),
        );

        $days = max(1, $start->diffInDays($end) + 1);

        $previousEnd = $start->copy()->subDay()->endOfDay();
        $previousStart = $previousEnd->copy()->subDays($days - 1)->startOfDay();

        return [
            'period' => $period,
            'branch_id' => $branchId,
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'max_date' => $maxDate,
            'label' => mb_convert_case($label, MB_CASE_TITLE, 'UTF-8'),
        ];
    }

    private function resolveDashboardMaxDate(Collection $accessibleBranchIds): Carbon
    {
        $salesMaxDate = Sale::query()
            ->whereIn('branch_id', $accessibleBranchIds)
            ->max('date');

        $movementMaxDate = DB::table('stock_movements')
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->whereIn('branch_products.branch_id', $accessibleBranchIds)
            ->max('stock_movements.created_at');

        return collect([$salesMaxDate, $movementMaxDate, now()])
            ->filter()
            ->map(fn ($date) => Carbon::parse($date))
            ->sortDesc()
            ->first()
            ->startOfDay();
    }

    private function dashboardWidgets(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return collect($this->periodRanges($start, $end))
            ->mapWithKeys(function (array $range, string $period) use ($branchIds) {
                $shrinkage = $this->shrinkageSummary($branchIds, $range['start'], $range['end']);

                return [
                    $period => [
                        'label' => $range['label'],
                        'salesTrend' => $this->salesTrend($branchIds, $period, $range['start'], $range['end']),
                        'productWeekdayRadar' => $this->productWeekdayRadar($branchIds, $range['start'], $range['end']),
                        'shrinkageByBranch' => $shrinkage['by_branch'],
                        'shrinkageByCategory' => $shrinkage['by_category'],
                        'shrinkageTimeline' => $this->shrinkageTimeline($branchIds, $period, $range['start'], $range['end']),
                        'shrinkageProducts' => $shrinkage['products'],
                        'shrinkageSummary' => [
                            'cost_loss' => $shrinkage['cost_loss'],
                            'revenue_loss' => $shrinkage['revenue_loss'],
                            'units' => $shrinkage['units'],
                        ],
                    ],
                ];
            })
            ->all();
    }

    private function periodRanges(Carbon $start, Carbon $end): array
    {
        $rangeLabel = sprintf(
            '%s - %s',
            $start->translatedFormat('d M Y'),
            $end->translatedFormat('d M Y'),
        );

        return [
            'day' => [
                'start' => $start->copy()->startOfDay(),
                'end' => $end->copy()->endOfDay(),
                'label' => "{$rangeLabel} por dia",
            ],
            'week' => [
                'start' => $start->copy()->startOfDay(),
                'end' => $end->copy()->endOfDay(),
                'label' => "{$rangeLabel} por semana",
            ],
            'month' => [
                'start' => $start->copy()->startOfDay(),
                'end' => $end->copy()->endOfDay(),
                'label' => "{$rangeLabel} por mes",
            ],
            'year' => [
                'start' => $start->copy()->startOfDay(),
                'end' => $end->copy()->endOfDay(),
                'label' => "{$rangeLabel} por anio",
            ],
        ];
    }

    private function summary(
        Collection $branchIds,
        Carbon $start,
        Carbon $end,
        Carbon $previousStart,
        Carbon $previousEnd,
        Collection $branchPerformance,
        array $lowRotationByBranch,
    ): array {
        $sales = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end]);

        $previousSales = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$previousStart, $previousEnd]);

        $revenue = (float) (clone $sales)->sum('total');
        $transactions = (int) (clone $sales)->count();
        $averageTicket = $transactions > 0
            ? round($revenue / $transactions, 2)
            : 0.0;

        $investment = $this->salesInvestment($branchIds, $start, $end);
        $previousRevenue = (float) (clone $previousSales)->sum('total');
        $profit = round($revenue - $investment, 2);
        $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0;
        $growth = $previousRevenue > 0
            ? round((($revenue - $previousRevenue) / $previousRevenue) * 100, 1)
            : ($revenue > 0 ? 100.0 : 0.0);

        $expectedProfit = $this->expectedProfit($branchIds);
        $expectedRevenue = $this->expectedRevenue($branchIds);
        $topBranch = $branchPerformance->first();
        $topProfitBranch = $branchPerformance->sortByDesc('profit')->first();
        $topLowRotationBranch = collect($lowRotationByBranch)->sortByDesc('products')->first();

        return [
            'revenue' => round($revenue, 2),
            'investment' => round($investment, 2),
            'profit' => $profit,
            'margin' => $margin,
            'transactions' => $transactions,
            'average_ticket' => round($averageTicket, 2),
            'previous_revenue' => round($previousRevenue, 2),
            'growth' => $growth,
            'expected_profit' => round($expectedProfit, 2),
            'expected_revenue' => round($expectedRevenue, 2),
            'top_branch' => $topBranch,
            'top_profit_branch' => $topProfitBranch,
            'top_low_rotation_branch' => $topLowRotationBranch,
            'total_branches' => $branchPerformance->count(),
            'low_rotation_products' => (int) collect($lowRotationByBranch)->sum('products'),
        ];
    }

    private function salesTrend(
        Collection $branchIds,
        string $period,
        Carbon $start,
        Carbon $end,
    ): array {
        return match ($period) {
            'day' => $this->salesTrendByDay($branchIds, $start, $end),
            'week' => $this->salesTrendByWeek($branchIds, $start, $end),
            'year' => $this->salesTrendByMonth($branchIds, $start, $end),
            default => $this->salesTrendByDay($branchIds, $start, $end),
        };
    }

    private function salesTrendByHour(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $rows = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('HOUR(date) as period_index')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as transactions')
            ->groupBy('period_index')
            ->orderBy('period_index')
            ->get()
            ->keyBy('period_index');

        return collect(range(0, 23))
            ->map(function ($hour) use ($rows, $start) {
                $row = $rows->get($hour);
                $date = $start->copy()->setTime($hour, 0);

                return [
                    'key' => $hour,
                    'label' => $date->format('H:i'),
                    'revenue' => round((float) ($row->revenue ?? 0), 2),
                    'investment' => 0,
                    'profit' => round((float) ($row->revenue ?? 0), 2),
                    'transactions' => (int) ($row->transactions ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function salesTrendByDay(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $salesRows = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('DATE(date) as period_key')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as transactions')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        $investmentRows = $this->salesInvestmentByDate($branchIds, $start, $end);

        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());

        return collect(range(0, $days))
            ->map(function ($offset) use ($start, $salesRows, $investmentRows) {
                $date = $start->copy()->addDays($offset)->toDateString();
                $revenue = (float) ($salesRows->get($date)?->revenue ?? 0);
                $investment = (float) ($investmentRows[$date] ?? 0);

                return [
                    'key' => $date,
                    'label' => Carbon::parse($date)->translatedFormat('d M'),
                    'revenue' => round($revenue, 2),
                    'investment' => round($investment, 2),
                    'profit' => round($revenue - $investment, 2),
                    'transactions' => (int) ($salesRows->get($date)?->transactions ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function salesTrendByWeek(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $salesRows = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('YEARWEEK(date, 1) as period_key')
            ->selectRaw('MIN(DATE(date)) as first_date')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as transactions')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        $investmentRows = $this->salesInvestmentByWeek($branchIds, $start, $end);
        $cursor = $start->copy()->startOfWeek();
        $weeks = collect();

        while ($cursor->lte($end)) {
            $key = (int) $cursor->format('oW');
            $row = $salesRows->get($key);
            $revenue = (float) ($row->revenue ?? 0);
            $investment = (float) ($investmentRows[$key] ?? 0);

            $weeks->push([
                'key' => $key,
                'label' => $cursor->translatedFormat('d M'),
                'revenue' => round($revenue, 2),
                'investment' => round($investment, 2),
                'profit' => round($revenue - $investment, 2),
                'transactions' => (int) ($row->transactions ?? 0),
            ]);

            $cursor->addWeek();
        }

        return $weeks->values()->all();
    }

    private function salesTrendByMonth(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $salesRows = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as period_key")
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as transactions')
            ->groupBy('period_key')
            ->orderBy('period_key')
            ->get()
            ->keyBy('period_key');

        $investmentRows = $this->salesInvestmentByMonth($branchIds, $start, $end);
        $cursor = $start->copy()->startOfMonth();
        $months = collect();

        while ($cursor->lte($end)) {
            $key = $cursor->format('Y-m');
            $revenue = (float) ($salesRows->get($key)?->revenue ?? 0);
            $investment = (float) ($investmentRows[$key] ?? 0);

            $months->push([
                'key' => $key,
                'label' => $cursor->translatedFormat('M Y'),
                'revenue' => round($revenue, 2),
                'investment' => round($investment, 2),
                'profit' => round($revenue - $investment, 2),
                'transactions' => (int) ($salesRows->get($key)?->transactions ?? 0),
            ]);

            $cursor->addMonth();
        }

        return $months->values()->all();
    }

    private function branchPerformance(Collection $branchIds, Carbon $start, Carbon $end): Collection
    {
        $rows = $this->salesBaseQuery($branchIds)
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->whereBetween('sales.date', [$start, $end])
            ->select('branches.id', 'branches.name', 'branches.slug')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as revenue')
            ->selectRaw('COUNT(sales.id) as transactions')
            ->selectRaw('COALESCE(AVG(sales.total), 0) as average_ticket')
            ->groupBy('branches.id', 'branches.name', 'branches.slug')
            ->orderByDesc('revenue')
            ->get()
            ->keyBy('id');

        $investmentByBranch = $this->salesInvestmentByBranch($branchIds, $start, $end);
        $expectedProfitByBranch = $this->expectedProfitByBranch($branchIds);

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(function (Branch $branch) use ($rows, $investmentByBranch, $expectedProfitByBranch) {
                $row = $rows->get($branch->id);
                $revenue = (float) ($row->revenue ?? 0);
                $investment = (float) ($investmentByBranch[$branch->id] ?? 0);
                $profit = $revenue - $investment;

                return [
                    'id' => (int) $branch->id,
                    'name' => $branch->name,
                    'slug' => $branch->slug,
                    'revenue' => round($revenue, 2),
                    'investment' => round($investment, 2),
                    'profit' => round($profit, 2),
                    'transactions' => (int) ($row->transactions ?? 0),
                    'average_ticket' => round((float) ($row->average_ticket ?? 0), 2),
                    'expected_profit' => round((float) ($expectedProfitByBranch[$branch->id] ?? 0), 2),
                    'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }

    private function expiringBatches(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return ProductBatch::query()
            ->with([
                'branchProduct.branch:id,name',
                'branchProduct.product:id,name,sale_price,unit',
            ])
            ->whereHas('branchProduct', fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereIn('status', ['ACTIVE', 'SEASONAL'])
            ->where('quantity', '>', 0)
            ->whereNotNull('expiration_date')
            ->whereBetween('expiration_date', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('expiration_date')
            ->limit(12)
            ->get()
            ->map(function (ProductBatch $batch) {
                $product = $batch->branchProduct?->product;
                $branch = $batch->branchProduct?->branch;
                $days = $batch->expiration_date
                    ? now()->startOfDay()->diffInDays($batch->expiration_date->copy()->startOfDay(), false)
                    : null;

                return [
                    'id' => $batch->id,
                    'product_name' => $product?->name ?? 'Producto',
                    'branch_name' => $branch?->name ?? 'Sucursal',
                    'lot_number' => $batch->lot_number,
                    'quantity' => round((float) $batch->quantity, 2),
                    'unit' => $product?->unit ?? 'pieza',
                    'expiration_date' => optional($batch->expiration_date)->toDateString(),
                    'days_to_expire' => $days,
                    'expected_revenue' => round((float) $batch->quantity * (float) ($product?->sale_price ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function lowRotationProducts(Collection $branchIds, Carbon $end, int $thresholdDays): array
    {
        $lastSaleSubquery = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->selectRaw('sales.branch_id, sale_details.product_id, MAX(sales.date) as last_sale_at')
            ->where('sales.status', 'completed')
            ->groupBy('sales.branch_id', 'sale_details.product_id');

        return BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->join('branches', 'branches.id', '=', 'branch_products.branch_id')
            ->leftJoinSub($lastSaleSubquery, 'last_sales', function ($join) {
                $join->on('last_sales.branch_id', '=', 'branch_products.branch_id')
                    ->on('last_sales.product_id', '=', 'branch_products.product_id');
            })
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->where('branch_products.stock', '>', 0)
            ->where(function ($query) use ($end, $thresholdDays) {
                $query->whereNull('last_sales.last_sale_at')
                    ->orWhere('last_sales.last_sale_at', '<=', $end->copy()->subDays($thresholdDays));
            })
            ->select([
                'branch_products.id',
                'products.name as product_name',
                'branches.name as branch_name',
                'branch_products.stock',
                'products.unit',
                'products.cost',
                'products.sale_price',
                'last_sales.last_sale_at',
            ])
            ->orderBy('last_sales.last_sale_at')
            ->orderByDesc('branch_products.stock')
            ->limit(12)
            ->get()
            ->map(function ($row) use ($end) {
                $lastSaleAt = $row->last_sale_at ? Carbon::parse($row->last_sale_at) : null;
                $daysWithoutSale = $lastSaleAt
                    ? $lastSaleAt->startOfDay()->diffInDays($end->copy()->startOfDay())
                    : null;
                $expectedProfit = ((float) $row->sale_price - (float) $row->cost) * (float) $row->stock;

                return [
                    'id' => (int) $row->id,
                    'product_name' => $row->product_name,
                    'branch_name' => $row->branch_name,
                    'stock' => round((float) $row->stock, 2),
                    'unit' => $row->unit ?? 'pieza',
                    'inventory_cost' => round((float) $row->cost * (float) $row->stock, 2),
                    'expected_profit' => round($expectedProfit, 2),
                    'last_sale_at' => $lastSaleAt?->toDateString(),
                    'days_without_sale' => $daysWithoutSale,
                ];
            })
            ->values()
            ->all();
    }

    private function lowRotationByBranch(Collection $branchIds, Carbon $end, int $thresholdDays): array
    {
        $lastSaleSubquery = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->selectRaw('sales.branch_id, sale_details.product_id, MAX(sales.date) as last_sale_at')
            ->where('sales.status', 'completed')
            ->groupBy('sales.branch_id', 'sale_details.product_id');

        $rows = BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->join('branches', 'branches.id', '=', 'branch_products.branch_id')
            ->leftJoinSub($lastSaleSubquery, 'last_sales', function ($join) {
                $join->on('last_sales.branch_id', '=', 'branch_products.branch_id')
                    ->on('last_sales.product_id', '=', 'branch_products.product_id');
            })
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->where('branch_products.stock', '>', 0)
            ->where(function ($query) use ($end, $thresholdDays) {
                $query->whereNull('last_sales.last_sale_at')
                    ->orWhere('last_sales.last_sale_at', '<=', $end->copy()->subDays($thresholdDays));
            })
            ->selectRaw('branches.id as branch_id')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('COUNT(branch_products.id) as products')
            ->selectRaw('COALESCE(SUM(branch_products.stock), 0) as stock')
            ->selectRaw('COALESCE(SUM(branch_products.stock * (products.sale_price - products.cost)), 0) as expected_profit')
            ->groupBy('branches.id', 'branches.name')
            ->get()
            ->keyBy('branch_id');

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Branch $branch) use ($rows) {
                $row = $rows->get($branch->id);

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'products' => (int) ($row->products ?? 0),
                    'stock' => round((float) ($row->stock ?? 0), 2),
                    'expected_profit' => round((float) ($row->expected_profit ?? 0), 2),
                ];
            })
            ->sortByDesc('products')
            ->values()
            ->all();
    }

    private function salesInvestment(Collection $branchIds, Carbon $start, Carbon $end): float
    {
        return (float) DB::table('purchase_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['GENERATED', 'REVIEW', 'COMPLETED'])
            ->whereBetween('generated_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(estimated_total), 0) as total')
            ->value('total');
    }

    private function salesInvestmentByDate(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return DB::table('purchase_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['GENERATED', 'REVIEW', 'COMPLETED'])
            ->whereBetween('generated_at', [$start, $end])
            ->selectRaw('DATE(generated_at) as period_key')
            ->selectRaw('COALESCE(SUM(estimated_total), 0) as total')
            ->groupBy('period_key')
            ->pluck('total', 'period_key')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function salesInvestmentByMonth(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return DB::table('purchase_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['GENERATED', 'REVIEW', 'COMPLETED'])
            ->whereBetween('generated_at', [$start, $end])
            ->selectRaw("DATE_FORMAT(generated_at, '%Y-%m') as period_key")
            ->selectRaw('COALESCE(SUM(estimated_total), 0) as total')
            ->groupBy('period_key')
            ->pluck('total', 'period_key')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function salesInvestmentByWeek(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return DB::table('purchase_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['GENERATED', 'REVIEW', 'COMPLETED'])
            ->whereBetween('generated_at', [$start, $end])
            ->selectRaw('YEARWEEK(generated_at, 1) as period_key')
            ->selectRaw('COALESCE(SUM(estimated_total), 0) as total')
            ->groupBy('period_key')
            ->pluck('total', 'period_key')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function salesInvestmentByBranch(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return DB::table('purchase_orders')
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', ['GENERATED', 'REVIEW', 'COMPLETED'])
            ->whereBetween('generated_at', [$start, $end])
            ->selectRaw('branch_id')
            ->selectRaw('COALESCE(SUM(estimated_total), 0) as total')
            ->groupBy('branch_id')
            ->pluck('total', 'branch_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function expectedRevenue(Collection $branchIds): float
    {
        return (float) BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->selectRaw('COALESCE(SUM(branch_products.stock * products.sale_price), 0) as total')
            ->value('total');
    }

    private function expectedProfit(Collection $branchIds): float
    {
        return (float) BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->selectRaw('COALESCE(SUM(branch_products.stock * (products.sale_price - products.cost)), 0) as total')
            ->value('total');
    }

    private function expectedProfitByBranch(Collection $branchIds): array
    {
        return BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('branch_products.status', BranchProduct::STATUS_ACTIVE)
            ->selectRaw('branch_products.branch_id as branch_id')
            ->selectRaw('COALESCE(SUM(branch_products.stock * (products.sale_price - products.cost)), 0) as total')
            ->groupBy('branch_products.branch_id')
            ->pluck('total', 'branch_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function purchaseSummary(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $purchaseRows = DB::table('purchases')
            ->join('branches', 'branches.id', '=', 'purchases.branch_id')
            ->whereIn('purchases.branch_id', $branchIds)
            ->where('purchases.status', 'completed')
            ->whereBetween('purchases.date', [$start, $end])
            ->selectRaw('branches.id as branch_id')
            ->selectRaw('branches.name as branch_name')
            ->selectRaw('COUNT(purchases.id) as purchases')
            ->selectRaw('COALESCE(SUM(purchases.total), 0) as total')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total')
            ->get();

        $topSuppliers = DB::table('purchases')
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->whereIn('purchases.branch_id', $branchIds)
            ->where('purchases.status', 'completed')
            ->whereBetween('purchases.date', [$start, $end])
            ->select('suppliers.id', 'suppliers.name')
            ->selectRaw('COUNT(purchases.id) as purchases')
            ->selectRaw('COALESCE(SUM(purchases.total), 0) as total')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'purchases' => (int) $row->purchases,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();

        $total = (float) $purchaseRows->sum('total');
        $count = (int) $purchaseRows->sum('purchases');

        return [
            'total' => round($total, 2),
            'count' => $count,
            'average_purchase' => $count > 0 ? round($total / $count, 2) : 0.0,
            'by_branch' => $purchaseRows
                ->map(fn ($row) => [
                    'branch_id' => (int) $row->branch_id,
                    'branch_name' => $row->branch_name,
                    'purchases' => (int) $row->purchases,
                    'total' => round((float) $row->total, 2),
                ])
                ->values()
                ->all(),
            'top_suppliers' => $topSuppliers,
        ];
    }

    private function customerBreakdown(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        return DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$start, $end])
            ->selectRaw('COALESCE(customers.id, 0) as id')
            ->selectRaw("COALESCE(customers.name, 'Cliente sin registrar') as name")
            ->selectRaw('COUNT(sales.id) as transactions')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as total')
            ->selectRaw('COALESCE(AVG(sales.total), 0) as average_ticket')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'transactions' => (int) $row->transactions,
                'total' => round((float) $row->total, 2),
                'average_ticket' => round((float) $row->average_ticket, 2),
            ])
            ->values()
            ->all();
    }

    private function discountSummary(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $baseQuery = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$start, $end]);

        $totals = (clone $baseQuery)
            ->selectRaw('COALESCE(SUM(sale_details.discount_amount * sale_details.quantity), 0) as discount_total')
            ->selectRaw('COUNT(CASE WHEN sale_details.discount_amount > 0 THEN 1 END) as discounted_lines')
            ->selectRaw('COUNT(sale_details.id) as detail_lines')
            ->first();

        $topProducts = (clone $baseQuery)
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->where('sale_details.discount_amount', '>', 0)
            ->select('products.id', 'products.name')
            ->selectRaw('COALESCE(SUM(sale_details.discount_amount * sale_details.quantity), 0) as discount_total')
            ->selectRaw('COALESCE(AVG(sale_details.discount_percentage), 0) as average_discount')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('discount_total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'discount_total' => round((float) $row->discount_total, 2),
                'average_discount' => round((float) $row->average_discount, 1),
            ])
            ->values()
            ->all();

        return [
            'discount_total' => round((float) ($totals->discount_total ?? 0), 2),
            'discounted_lines' => (int) ($totals->discounted_lines ?? 0),
            'lines' => (int) ($totals->detail_lines ?? 0),
            'top_products' => $topProducts,
        ];
    }

    private function shrinkageTimeline(Collection $branchIds, string $period, Carbon $start, Carbon $end): array
    {
        $periodKey = $this->periodKeyExpression('stock_movements.created_at', $period);

        return DB::table('stock_movements')
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->whereIn('stock_movements.reason', [
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
            ])
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->selectRaw("{$periodKey} as period_key")
            ->selectRaw("COALESCE(categories.name, 'Sin categoria') as category_name")
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.cost), 0) as cost_loss')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.sale_price), 0) as revenue_loss')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as units')
            ->groupBy('period_key', 'categories.name')
            ->orderBy('period_key')
            ->get()
            ->map(fn ($row) => [
                'period_key' => (string) $row->period_key,
                'label' => $this->periodLabel((string) $row->period_key, $period),
                'category_name' => $row->category_name,
                'cost_loss' => round((float) $row->cost_loss, 2),
                'revenue_loss' => round((float) $row->revenue_loss, 2),
                'units' => round((float) $row->units, 2),
            ])
            ->values()
            ->all();
    }

    private function productWeekdayRadar(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $topProduct = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$start, $end])
            ->select('products.id', 'products.name')
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('units')
            ->first();

        if (!$topProduct) {
            return [
                'product' => null,
                'weekdays' => ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'],
                'branches' => [],
            ];
        }

        $rows = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->where('sale_details.product_id', $topProduct->id)
            ->whereBetween('sales.date', [$start, $end])
            ->select('branches.id as branch_id', 'branches.name as branch_name')
            ->selectRaw('DAYOFWEEK(sales.date) - 1 as weekday_index')
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->groupBy('branches.id', 'branches.name', 'weekday_index')
            ->get();

        return [
            'product' => [
                'id' => (int) $topProduct->id,
                'name' => $topProduct->name,
                'units' => round((float) $topProduct->units, 2),
            ],
            'weekdays' => ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'],
            'branches' => $rows
                ->groupBy('branch_id')
                ->map(function ($branchRows) {
                    $first = $branchRows->first();
                    $unitsByDay = $branchRows
                        ->pluck('units', 'weekday_index')
                        ->map(fn ($value) => round((float) $value, 2));

                    return [
                        'branch_id' => (int) $first->branch_id,
                        'branch_name' => $first->branch_name,
                        'units' => collect(range(0, 6))
                            ->map(fn ($index) => (float) ($unitsByDay[$index] ?? 0))
                            ->values()
                            ->all(),
                        'total_units' => round((float) $branchRows->sum('units'), 2),
                    ];
                })
                ->sortByDesc('total_units')
                ->values()
                ->take(5)
                ->all(),
        ];
    }

    private function shrinkageSummary(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('stock_movements')
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->join('branches', 'branches.id', '=', 'branch_products.branch_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->whereIn('stock_movements.reason', [
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
            ])
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->select(
                'branches.id as branch_id',
                'branches.name as branch_name',
                'products.id as product_id',
                'products.name as product_name',
                'stock_movements.reason',
            )
            ->selectRaw("COALESCE(categories.name, 'Sin categoria') as category_name")
            ->selectRaw('COUNT(stock_movements.id) as movements')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as units')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.cost), 0) as cost_loss')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.sale_price), 0) as revenue_loss')
            ->groupBy(
                'branches.id',
                'branches.name',
                'products.id',
                'products.name',
                'categories.name',
                'stock_movements.reason',
            )
            ->orderByDesc('cost_loss')
            ->get();

        return [
            'cost_loss' => round((float) $rows->sum('cost_loss'), 2),
            'revenue_loss' => round((float) $rows->sum('revenue_loss'), 2),
            'units' => round((float) $rows->sum('units'), 2),
            'by_branch' => $rows
                ->groupBy('branch_id')
                ->map(function ($branchRows) {
                    $first = $branchRows->first();

                    return [
                        'branch_id' => (int) $first->branch_id,
                        'branch_name' => $first->branch_name,
                        'cost_loss' => round((float) $branchRows->sum('cost_loss'), 2),
                        'revenue_loss' => round((float) $branchRows->sum('revenue_loss'), 2),
                        'units' => round((float) $branchRows->sum('units'), 2),
                    ];
                })
                ->sortByDesc('cost_loss')
                ->values()
                ->all(),
            'by_category' => $rows
                ->groupBy('category_name')
                ->map(fn ($categoryRows, $categoryName) => [
                    'category_name' => $categoryName,
                    'cost_loss' => round((float) $categoryRows->sum('cost_loss'), 2),
                    'revenue_loss' => round((float) $categoryRows->sum('revenue_loss'), 2),
                    'units' => round((float) $categoryRows->sum('units'), 2),
                    'products' => (int) $categoryRows->pluck('product_id')->unique()->count(),
                ])
                ->sortByDesc('cost_loss')
                ->values()
                ->all(),
            'products' => $rows
                ->groupBy(fn ($row) => "{$row->category_name}|{$row->product_id}")
                ->map(function ($productRows) {
                    $first = $productRows->first();

                    return [
                        'product_id' => (int) $first->product_id,
                        'product_name' => $first->product_name,
                        'category_name' => $first->category_name,
                        'cost_loss' => round((float) $productRows->sum('cost_loss'), 2),
                        'revenue_loss' => round((float) $productRows->sum('revenue_loss'), 2),
                        'units' => round((float) $productRows->sum('units'), 2),
                    ];
                })
                ->sortByDesc('cost_loss')
                ->values()
                ->all(),
            'by_reason' => $rows
                ->groupBy('reason')
                ->map(fn ($reasonRows, $reason) => [
                    'reason' => $reason,
                    'cost_loss' => round((float) $reasonRows->sum('cost_loss'), 2),
                    'revenue_loss' => round((float) $reasonRows->sum('revenue_loss'), 2),
                    'units' => round((float) $reasonRows->sum('units'), 2),
                ])
                ->values()
                ->all(),
        ];
    }

    private function inventoryCoverage(Collection $branchIds, Carbon $end): array
    {
        $windowStart = $end->copy()->subDays(90)->startOfDay();
        $days = max(1, $windowStart->diffInDays($end));

        $salesUnitsByBranch = DB::table('sale_details')
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereIn('sales.branch_id', $branchIds)
            ->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$windowStart, $end])
            ->selectRaw('sales.branch_id as branch_id')
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->groupBy('sales.branch_id')
            ->pluck('units', 'branch_id')
            ->map(fn ($value) => (float) $value);

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (Branch $branch) use ($salesUnitsByBranch, $days) {
                $stockUnits = (float) BranchProduct::query()
                    ->where('branch_id', $branch->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->sum('stock');
                $soldUnits = (float) ($salesUnitsByBranch[$branch->id] ?? 0);
                $dailyAverage = $soldUnits > 0 ? $soldUnits / $days : 0.0;

                return [
                    'branch_id' => (int) $branch->id,
                    'branch_name' => $branch->name,
                    'stock_units' => round($stockUnits, 2),
                    'sold_units_90d' => round($soldUnits, 2),
                    'daily_average' => round($dailyAverage, 2),
                    'coverage_days' => $dailyAverage > 0 ? round($stockUnits / $dailyAverage, 1) : null,
                ];
            })
            ->sortBy('coverage_days')
            ->values()
            ->all();
    }

    private function annualSummary(Collection $branchIds, int $year): array
    {
        $start = Carbon::create($year, 1, 1)->startOfYear();
        $end = Carbon::create($year, 12, 31)->endOfYear();

        $salesRows = $this->salesBaseQuery($branchIds)
            ->whereBetween('date', [$start, $end])
            ->selectRaw('MONTH(date) as month')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COUNT(*) as transactions')
            ->groupBy('month')
            ->pluck('revenue', 'month')
            ->map(fn ($value) => (float) $value);

        $purchaseRows = DB::table('purchases')
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed')
            ->whereBetween('date', [$start, $end])
            ->selectRaw('MONTH(date) as month')
            ->selectRaw('COALESCE(SUM(total), 0) as purchases')
            ->groupBy('month')
            ->pluck('purchases', 'month')
            ->map(fn ($value) => (float) $value);

        $shrinkageRows = DB::table('stock_movements')
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->whereIn('stock_movements.reason', [
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
            ])
            ->whereBetween('stock_movements.created_at', [$start, $end])
            ->selectRaw('MONTH(stock_movements.created_at) as month')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.cost), 0) as shrinkage')
            ->groupBy('month')
            ->pluck('shrinkage', 'month')
            ->map(fn ($value) => (float) $value);

        $investmentRows = $this->salesInvestmentByMonth($branchIds, $start, $end);

        return collect(range(1, 12))
            ->map(function ($month) use ($year, $salesRows, $purchaseRows, $shrinkageRows, $investmentRows) {
                $revenue = (float) ($salesRows[$month] ?? 0);
                $investment = (float) ($investmentRows[$month] ?? 0);

                return [
                    'month' => $month,
                    'label' => Carbon::create($year, $month, 1)->translatedFormat('M'),
                    'revenue' => round($revenue, 2),
                    'investment' => round($investment, 2),
                    'profit' => round($revenue - $investment, 2),
                    'purchases' => round((float) ($purchaseRows[$month] ?? 0), 2),
                    'shrinkage' => round((float) ($shrinkageRows[$month] ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function rotationThreshold(string $period): int
    {
        return match ($period) {
            'day' => 21,
            'year' => 120,
            default => 45,
        };
    }

    private function periodKeyExpression(string $column, string $period): string
    {
        return match ($period) {
            'week' => "YEARWEEK({$column}, 1)",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            'year' => "YEAR({$column})",
            default => "DATE({$column})",
        };
    }

    private function periodLabel(string $key, string $period): string
    {
        return match ($period) {
            'week' => Carbon::now()
                ->setISODate((int) substr($key, 0, 4), (int) substr($key, 4, 2))
                ->translatedFormat('d M'),
            'month' => Carbon::createFromFormat('Y-m-d', "{$key}-01")->translatedFormat('M Y'),
            'year' => $key,
            default => Carbon::parse($key)->translatedFormat('d M'),
        };
    }

    private function salesBaseQuery(Collection $branchIds)
    {
        return Sale::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'completed');
    }
}
