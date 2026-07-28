<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reúne las métricas financieras del tablero. Todas las sumas se calculan en
 * la base de datos; la interfaz recibe únicamente los totales ya preparados.
 */
class DashboardMetricsService
{
    public function payload(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $previousEnd = $start->copy()->subSecond();
        $previousStart = $previousEnd->copy()->subSeconds($start->diffInSeconds($end));

        $current = $this->metrics($branchIds, $start, $end);
        $previous = $this->metrics($branchIds, $previousStart, $previousEnd);

        return [
            'summary' => $this->summary($current, $previous),
            'series' => $this->series($branchIds, $start, $end),
            'shrinkage_by_category' => $this->shrinkageByCategory($branchIds, $start, $end),
            'product_sales' => null,
            'limitations' => [
                // El dominio conserva el precio histórico de venta, pero no el costo
                // ligado a cada detalle de venta ni a cada salida por merma.
                'profit_uses_current_cost' => false,
                'shrinkage_uses_current_cost' => false,
            ],
        ];
    }

    public function searchProducts(Collection $branchIds, Carbon $start, Carbon $end, string $term): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < 2) {
            return collect();
        }

        return DB::table('products')
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->whereIn('branch_products.branch_id', $branchIds)
            ->where('products.active', true)
            ->where(function (Builder $query) use ($term) {
                $query->where('products.name', 'like', "%{$term}%")
                    ->orWhereExists(function (Builder $barcodeQuery) use ($term) {
                        $barcodeQuery->selectRaw('1')
                            ->from('barcodes')
                            ->whereColumn('barcodes.product_id', 'products.id')
                            ->where('barcodes.code', 'like', "%{$term}%");
                    });
            })
            ->select('products.id', 'products.name')
            ->selectRaw('MIN((SELECT code FROM barcodes WHERE barcodes.product_id = products.id ORDER BY id LIMIT 1)) as code')
            ->groupBy('products.id', 'products.name')
            ->orderBy('products.name')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'name' => $row->name,
                'code' => $row->code,
            ]);
    }

    private function metrics(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $sales = $this->confirmedSales($branchIds, $start, $end)
            ->selectRaw('COALESCE(SUM(sales.total), 0) as amount')
            ->selectRaw('COUNT(sales.id) as transactions')
            ->first();

        $cost = $this->saleCostQuery($branchIds, $start, $end)
            ->selectRaw('COALESCE(SUM(sale_details.quantity * COALESCE(sale_details.unit_cost, products.cost)), 0) as amount')
            ->value('amount');

        $investment = $this->investmentQuery($branchIds, $start, $end)
            ->selectRaw('COALESCE(SUM(purchase_orders.actual_total), 0) as amount')
            ->value('amount');

        $shrinkage = $this->shrinkageQuery($branchIds, $start, $end)
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * COALESCE(stock_movements.unit_cost, products.cost)), 0) as amount')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as units')
            ->first();

        $salesAmount = round((float) ($sales->amount ?? 0), 2);
        $costAmount = round((float) $cost, 2);

        return [
            'sales' => $salesAmount,
            'transactions' => (int) ($sales->transactions ?? 0),
            'investment' => round((float) $investment, 2),
            'profit' => round($salesAmount - $costAmount, 2),
            'shrinkage' => round((float) ($shrinkage->amount ?? 0), 2),
            'shrinkage_units' => round((float) ($shrinkage->units ?? 0), 3),
        ];
    }

    private function summary(array $current, array $previous): array
    {
        return collect(['sales', 'investment', 'profit', 'shrinkage'])
            ->mapWithKeys(function (string $metric) use ($current, $previous) {
                $value = (float) $current[$metric];
                $previousValue = (float) $previous[$metric];

                return [$metric => [
                    'value' => $value,
                    'previous_value' => $previousValue,
                    'change' => $previousValue != 0
                        ? round((($value - $previousValue) / abs($previousValue)) * 100, 1)
                        : null,
                ]];
            })
            ->merge([
                'transactions' => $current['transactions'],
                'margin' => $current['sales'] > 0
                    ? round(($current['profit'] / $current['sales']) * 100, 1)
                    : 0,
                'shrinkage_units' => $current['shrinkage_units'],
            ])
            ->all();
    }

    private function series(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $sales = $this->confirmedSales($branchIds, $start, $end)
            ->selectRaw('DATE(sales.date) as date_key, COALESCE(SUM(sales.total), 0) as amount')
            ->groupBy('date_key')->pluck('amount', 'date_key');
        $costs = $this->saleCostQuery($branchIds, $start, $end)
            ->selectRaw('DATE(sales.date) as date_key, COALESCE(SUM(sale_details.quantity * COALESCE(sale_details.unit_cost, products.cost)), 0) as amount')
            ->groupBy('date_key')->pluck('amount', 'date_key');
        $investment = $this->investmentQuery($branchIds, $start, $end)
            ->selectRaw('DATE(purchase_orders.completed_at) as date_key, COALESCE(SUM(purchase_orders.actual_total), 0) as amount')
            ->groupBy('date_key')->pluck('amount', 'date_key');
        $shrinkage = $this->shrinkageQuery($branchIds, $start, $end)
            ->selectRaw('DATE(stock_movements.created_at) as date_key, COALESCE(SUM(ABS(stock_movements.quantity) * COALESCE(stock_movements.unit_cost, products.cost)), 0) as amount')
            ->groupBy('date_key')->pluck('amount', 'date_key');

        return $this->dates($start, $end)->map(function (Carbon $date) use ($sales, $costs, $investment, $shrinkage) {
            $key = $date->toDateString();
            $salesAmount = (float) ($sales[$key] ?? 0);

            return [
                'date' => $key,
                'label' => $date->translatedFormat('d M'),
                'sales' => round($salesAmount, 2),
                'investment' => round((float) ($investment[$key] ?? 0), 2),
                'profit' => round($salesAmount - (float) ($costs[$key] ?? 0), 2),
                'shrinkage' => round((float) ($shrinkage[$key] ?? 0), 2),
            ];
        })->all();
    }

    public function branchSeries(Collection $branches, Carbon $start, Carbon $end, string $grouping = 'day'): array
    {
        $branchIds = $branches->pluck('id');
        $sales = $this->confirmedSales($branchIds, $start, $end)
            ->selectRaw('sales.branch_id, DATE(sales.date) as date_key, COALESCE(SUM(sales.total), 0) as amount')
            ->groupBy('sales.branch_id', 'date_key')->get()->keyBy(fn ($row) => "{$row->branch_id}|{$row->date_key}");
        $costs = $this->saleCostQuery($branchIds, $start, $end)
            ->selectRaw('sales.branch_id, DATE(sales.date) as date_key, COALESCE(SUM(sale_details.quantity * COALESCE(sale_details.unit_cost, products.cost)), 0) as amount')
            ->groupBy('sales.branch_id', 'date_key')->get()->keyBy(fn ($row) => "{$row->branch_id}|{$row->date_key}");
        $investment = $this->investmentQuery($branchIds, $start, $end)
            ->selectRaw('purchase_orders.branch_id, DATE(purchase_orders.completed_at) as date_key, COALESCE(SUM(purchase_orders.actual_total), 0) as amount')
            ->groupBy('purchase_orders.branch_id', 'date_key')->get()->keyBy(fn ($row) => "{$row->branch_id}|{$row->date_key}");
        $shrinkage = $this->shrinkageQuery($branchIds, $start, $end)
            ->selectRaw('branch_products.branch_id, DATE(stock_movements.created_at) as date_key, COALESCE(SUM(ABS(stock_movements.quantity) * COALESCE(stock_movements.unit_cost, products.cost)), 0) as amount')
            ->groupBy('branch_products.branch_id', 'date_key')->get()->keyBy(fn ($row) => "{$row->branch_id}|{$row->date_key}");

        return $branches->map(function (Branch $branch) use ($start, $end, $grouping, $sales, $costs, $investment, $shrinkage) {
            return [
                'branch_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'series' => $this->buckets($start, $end, $grouping)->map(function (array $bucket) use ($branch, $sales, $costs, $investment, $shrinkage) {
                    $amounts = $bucket['dates']->reduce(function (array $total, Carbon $date) use ($branch, $sales, $costs, $investment, $shrinkage) {
                        $key = $branch->id.'|'.$date->toDateString();

                        $total['sales'] += (float) ($sales[$key]->amount ?? 0);
                        $total['investment'] += (float) ($investment[$key]->amount ?? 0);
                        $total['cost'] += (float) ($costs[$key]->amount ?? 0);
                        $total['shrinkage'] += (float) ($shrinkage[$key]->amount ?? 0);

                        return $total;
                    }, ['sales' => 0, 'investment' => 0, 'cost' => 0, 'shrinkage' => 0]);

                    return [
                        'label' => $bucket['label'],
                        'sales' => round($amounts['sales'], 2),
                        'investment' => round($amounts['investment'], 2),
                        'profit' => round($amounts['sales'] - $amounts['cost'], 2),
                        'shrinkage' => round($amounts['shrinkage'], 2),
                    ];
                })->all(),
            ];
        })->values()->all();
    }

    public function salesRanking(Collection $branches, Carbon $start, Carbon $end): array
    {
        if ($branches->isEmpty()) {
            return [];
        }

        $totals = $this->confirmedSales($branches->pluck('id'), $start, $end)
            ->select('sales.branch_id')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as sales')
            ->selectRaw('COUNT(sales.id) as transactions')
            ->groupBy('sales.branch_id')
            ->get()
            ->keyBy('branch_id');

        return $branches->map(function (Branch $branch) use ($totals) {
            $total = $totals->get($branch->id);

            return [
                'branch_id' => (int) $branch->id,
                'branch_name' => $branch->name,
                'sales' => round((float) ($total->sales ?? 0), 2),
                'transactions' => (int) ($total->transactions ?? 0),
            ];
        })->sortByDesc('sales')->values()->map(function (array $branch, int $index) {
            return $branch + ['rank' => $index + 1];
        })->all();
    }

    public function productRadar(Collection $branches, Carbon $start, Carbon $end, int $productId): array
    {
        $product = DB::table('products')->where('id', $productId)->first(['id', 'name']);
        if (! $product || $branches->isEmpty()) {
            return ['product' => null, 'mode' => 'radar', 'rows' => []];
        }

        $totals = $this->saleDetailsQuery($branches->pluck('id'), $start, $end)
            ->where('sale_details.product_id', $productId)
            ->select('sales.branch_id')
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(sale_details.subtotal), 0) as revenue')
            ->groupBy('sales.branch_id')
            ->get()
            ->keyBy('branch_id');

        $rows = $branches->map(function (Branch $branch) use ($totals) {
            $total = $totals->get($branch->id);

            return [
                'branch_id' => (int) $branch->id,
                'label' => $branch->name,
                'units' => round((float) ($total->units ?? 0), 3),
                'revenue' => round((float) ($total->revenue ?? 0), 2),
            ];
        })->values()->all();

        return ['product' => ['id' => (int) $product->id, 'name' => $product->name], 'mode' => 'radar', 'rows' => $rows];
    }

    public function categorySales(Collection $branches, Carbon $start, Carbon $end, Collection $categories): array
    {
        if ($branches->isEmpty() || $categories->isEmpty()) {
            return [];
        }

        $totals = $this->saleDetailsQuery($branches->pluck('id'), $start, $end)
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->whereIn('products.category_id', $categories->pluck('id'))
            ->select('products.category_id')
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->selectRaw('COALESCE(SUM(sale_details.subtotal), 0) as revenue')
            ->groupBy('products.category_id')
            ->get()
            ->keyBy('category_id');

        return $categories->map(function ($category) use ($totals) {
            $total = $totals->get($category->id);

            return [
                'category_id' => (int) $category->id,
                'label' => $category->name,
                'units' => round((float) ($total->units ?? 0), 3),
                'revenue' => round((float) ($total->revenue ?? 0), 2),
            ];
        })->values()->all();
    }

    private function shrinkageByCategory(Collection $branchIds, Carbon $start, Carbon $end): array
    {
        $rows = $this->shrinkageQuery($branchIds, $start, $end)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->selectRaw("COALESCE(categories.name, 'Sin categoría') as category")
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity) * products.cost), 0) as amount')
            ->selectRaw('COALESCE(SUM(ABS(stock_movements.quantity)), 0) as units')
            ->groupBy('categories.id', 'categories.name')->orderByDesc('amount')->get();
        $total = max(0.0, (float) $rows->sum('amount'));

        return $rows->map(fn ($row) => ['category' => $row->category, 'amount' => round((float) $row->amount, 2), 'units' => round((float) $row->units, 3), 'percentage' => $total > 0 ? round(((float) $row->amount / $total) * 100, 1) : 0])->all();
    }

    private function confirmedSales(Collection $branchIds, Carbon $start, Carbon $end)
    {
        return Sale::query()->whereIn('sales.branch_id', $branchIds)->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$start, $end])->where($this->confirmedSaleExists());
    }

    private function saleCostQuery(Collection $branchIds, Carbon $start, Carbon $end): Builder
    {
        return $this->saleDetailsQuery($branchIds, $start, $end)->join('products', 'products.id', '=', 'sale_details.product_id');
    }

    private function saleDetailsQuery(Collection $branchIds, Carbon $start, Carbon $end): Builder
    {
        return DB::table('sale_details')->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereIn('sales.branch_id', $branchIds)->where('sales.status', 'completed')
            ->whereBetween('sales.date', [$start, $end])->where($this->confirmedSaleExists());
    }

    private function confirmedSaleExists(): \Closure
    {
        return fn ($query) => $query->whereExists(fn ($closure) => $closure->selectRaw('1')->from('cash_register_closures')
            ->whereNull('cash_register_closures.deleted_at')->whereColumn('cash_register_closures.branch_id', 'sales.branch_id')
            ->whereRaw("COALESCE(cash_register_closures.cash_box_number, '1') = COALESCE(sales.cash_box_number, '1')")
            ->whereColumn('sales.date', '>', 'cash_register_closures.period_start')->whereColumn('sales.date', '<=', 'cash_register_closures.period_end'));
    }

    private function investmentQuery(Collection $branchIds, Carbon $start, Carbon $end): Builder
    {
        return DB::table('purchase_orders')
            ->whereIn('purchase_orders.branch_id', $branchIds)
            ->where('purchase_orders.status', 'COMPLETED')
            ->where('purchase_orders.actual_total', '>', 0)
            ->whereBetween('purchase_orders.completed_at', [$start, $end]);
    }

    private function shrinkageQuery(Collection $branchIds, Carbon $start, Carbon $end): Builder
    {
        return DB::table('stock_movements')->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')->whereIn('branch_products.branch_id', $branchIds)
            ->where('stock_movements.type', StockMovement::TYPE_OUT)->whereIn('stock_movements.reason', [StockMovement::REASON_DAMAGED, StockMovement::REASON_EXPIRED])
            ->whereBetween('stock_movements.created_at', [$start, $end]);
    }

    private function dates(Carbon $start, Carbon $end): Collection
    {
        $dates = collect();
        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            $dates->push($date->copy());
        }

return $dates;
    }

    private function buckets(Carbon $start, Carbon $end, string $grouping): Collection
    {
        $grouping = in_array($grouping, ['day', 'week', 'month'], true) ? $grouping : 'day';

        return $this->dates($start, $end)
            ->groupBy(fn (Carbon $date) => match ($grouping) {
                'week' => $date->copy()->startOfWeek()->toDateString(),
                'month' => $date->format('Y-m'),
                default => $date->toDateString(),
            })
            ->map(function (Collection $dates) use ($grouping) {
                $date = $dates->first();

                return [
                    'dates' => $dates,
                    'label' => match ($grouping) {
                        'week' => 'Sem. '.$date->isoWeek().' '.$date->year,
                        'month' => ucfirst($date->translatedFormat('M Y')),
                        default => $date->translatedFormat('d M'),
                    },
                ];
            })
            ->values();
    }
}
