<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function summary(Branch $branch): array
    {
        $productSummary = BranchProduct::query()
            ->where('branch_id', $branch->id)
            ->selectRaw('
                COUNT(*) as total_products,
                COALESCE(SUM(stock), 0) as total_stock,
                SUM(CASE WHEN stock > 0 AND stock <= min_stock THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock
            ')
            ->first();

        $batchSummary = ProductBatch::query()
            ->join('branch_products', 'branch_products.id', '=', 'product_batches.branch_product_id')
            ->where('branch_products.branch_id', $branch->id)
            ->selectRaw('
                SUM(CASE WHEN product_batches.expiration_date IS NOT NULL AND product_batches.expiration_date < CURDATE() THEN 1 ELSE 0 END) as expired_batches,
                SUM(CASE WHEN product_batches.expiration_date IS NOT NULL AND product_batches.expiration_date >= CURDATE() AND product_batches.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as near_expiration_batches
            ')
            ->first();

        $lastMovementSubquery = StockMovement::query()
            ->select(
                'branch_product_id',
                DB::raw('MAX(created_at) as last_out_at')
            )
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $expiredBatchSubquery = ProductBatch::query()
            ->select(
                'branch_product_id',
                DB::raw('COUNT(*) as expired_batches')
            )
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today())
            ->groupBy('branch_product_id');

        $nearExpirationBatchSubquery = ProductBatch::query()
            ->select(
                'branch_product_id',
                DB::raw('COUNT(*) as near_expiration_batches')
            )
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', today())
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->groupBy('branch_product_id');

        $attentionProducts = BranchProduct::query()
            ->leftJoinSub($lastMovementSubquery, 'last_movements', function ($join) {
                $join->on('last_movements.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($expiredBatchSubquery, 'expired_batches', function ($join) {
                $join->on('expired_batches.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($nearExpirationBatchSubquery, 'near_expiration_batches', function ($join) {
                $join->on('near_expiration_batches.branch_product_id', '=', 'branch_products.id');
            })
            ->where('branch_products.branch_id', $branch->id)
            ->where(function ($query) {
                $query
                    ->where('branch_products.stock', '<=', 0)
                    ->orWhereColumn('branch_products.stock', '<=', 'branch_products.min_stock')
                    ->orWhereRaw('DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90')
                    ->orWhereRaw('COALESCE(expired_batches.expired_batches, 0) > 0')
                    ->orWhereRaw('COALESCE(near_expiration_batches.near_expiration_batches, 0) > 0');
            })
            ->count();

        return [
            'total_products' => (int) ($productSummary->total_products ?? 0),
            'total_stock' => (float) ($productSummary->total_stock ?? 0),
            'low_stock' => (int) ($productSummary->low_stock ?? 0),
            'out_of_stock' => (int) ($productSummary->out_of_stock ?? 0),
            'expired_batches' => (int) ($batchSummary->expired_batches ?? 0),
            'near_expiration_batches' => (int) ($batchSummary->near_expiration_batches ?? 0),
            'attention_products' => (int) $attentionProducts,
        ];
    }

    public function movements(Branch $branch, array $filters)
    {
        $query = StockMovement::query()
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->where('branch_products.branch_id', $branch->id)
            ->select([
                'stock_movements.id',
                DB::raw('DATE(stock_movements.created_at) as date'),
                DB::raw('TIME_FORMAT(stock_movements.created_at, "%H:%i") as time'),
                'users.name as user',
                'products.name as product',
                'categories.name as category',
                'stock_movements.type',
                'stock_movements.reason',
                'stock_movements.quantity',
                'stock_movements.previous_stock',
                'stock_movements.new_stock',
                'stock_movements.notes',
            ])
            ->latest('stock_movements.created_at');

        $this->applyPeriod($query, $filters, 'stock_movements.created_at');
        $this->applyCommonFilters($query, $filters);

        return $query->limit(100)->get();
    }

    public function expirations(Branch $branch, array $filters)
    {
        $query = ProductBatch::query()
            ->join('branch_products', 'branch_products.id', '=', 'product_batches.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('branch_products.branch_id', $branch->id)
            ->whereNotNull('product_batches.expiration_date')
            ->whereDate('product_batches.expiration_date', '<=', now()->addDays(30))
            ->select([
                'product_batches.id',
                'products.name as product',
                'categories.name as category',
                'product_batches.lot_number',
                'product_batches.quantity',
                'product_batches.expiration_date',
                DB::raw('DATEDIFF(product_batches.expiration_date, CURDATE()) as days_to_expire'),
                DB::raw('
                    CASE
                        WHEN product_batches.expiration_date < CURDATE() THEN "EXPIRED"
                        ELSE "NEAR_EXPIRATION"
                    END as expiration_status
                '),
                DB::raw('
                    CASE
                        WHEN product_batches.expiration_date < CURDATE() THEN "Caducado"
                        ELSE "Próximo a caducar"
                    END as expiration_human_text
                '),
            ])
            ->orderBy('product_batches.expiration_date');

        if (!empty($filters['product_id'])) {
            $query->where('branch_products.id', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        return $query->limit(100)->get();
    }

    public function rotation(Branch $branch, array $filters)
    {
        $periodMovementSubquery = StockMovement::query()
            ->select(
                'branch_product_id',
                DB::raw('SUM(quantity) as total_out')
            )
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $this->applyPeriod($periodMovementSubquery, $filters);

        $lastMovementSubquery = StockMovement::query()
            ->select(
                'branch_product_id',
                DB::raw('MAX(created_at) as last_out_at')
            )
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $query = BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($periodMovementSubquery, 'period_movements', function ($join) {
                $join->on('period_movements.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($lastMovementSubquery, 'last_movements', function ($join) {
                $join->on('last_movements.branch_product_id', '=', 'branch_products.id');
            })
            ->where('branch_products.branch_id', $branch->id)
            ->select([
                'branch_products.id as branch_product_id',
                'products.name as product',
                'categories.name as category',
                'branch_products.stock',
                'branch_products.min_stock',
                DB::raw('COALESCE(period_movements.total_out, 0) as total_out'),
                DB::raw('DATE(last_movements.last_out_at) as last_out_at'),
                DB::raw('DATEDIFF(CURDATE(), last_movements.last_out_at) as days_without_movement'),
                DB::raw('
                    CASE
                        WHEN COALESCE(period_movements.total_out, 0) <= 0 THEN "NO_MOVEMENT"
                        WHEN DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90 THEN "LOW"
                        WHEN COALESCE(period_movements.total_out, 0) >= 30 THEN "HIGH"
                        WHEN COALESCE(period_movements.total_out, 0) >= 10 THEN "MEDIUM"
                        ELSE "LOW"
                    END as rotation_level
                '),
            ]);

        if (!empty($filters['product_id'])) {
            $query->where('branch_products.id', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        return $query->limit(300)->get();
    }

    public function attentionProducts(Branch $branch)
    {
        $lastMovementSubquery = StockMovement::query()
            ->select(
                'branch_product_id',
                DB::raw('MAX(created_at) as last_out_at')
            )
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $expiredBatchSubquery = ProductBatch::query()
            ->select(
                'branch_product_id',
                DB::raw('COUNT(*) as expired_batches')
            )
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today())
            ->groupBy('branch_product_id');

        $nearExpirationBatchSubquery = ProductBatch::query()
            ->select(
                'branch_product_id',
                DB::raw('COUNT(*) as near_expiration_batches')
            )
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', today())
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->groupBy('branch_product_id');

        return BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($lastMovementSubquery, 'last_movements', function ($join) {
                $join->on('last_movements.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($expiredBatchSubquery, 'expired_batches', function ($join) {
                $join->on('expired_batches.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($nearExpirationBatchSubquery, 'near_expiration_batches', function ($join) {
                $join->on('near_expiration_batches.branch_product_id', '=', 'branch_products.id');
            })
            ->where('branch_products.branch_id', $branch->id)
            ->where(function ($query) {
                $query
                    ->where('branch_products.stock', '<=', 0)
                    ->orWhereColumn('branch_products.stock', '<=', 'branch_products.min_stock')
                    ->orWhereRaw('DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90')
                    ->orWhereRaw('COALESCE(expired_batches.expired_batches, 0) > 0')
                    ->orWhereRaw('COALESCE(near_expiration_batches.near_expiration_batches, 0) > 0');
            })
            ->select([
                'branch_products.id as branch_product_id',
                'products.name as product',
                'categories.name as category',
                'branch_products.stock',
                'branch_products.min_stock',
                DB::raw('DATE(last_movements.last_out_at) as last_out_at'),
                DB::raw('DATEDIFF(CURDATE(), last_movements.last_out_at) as days_without_movement'),
                DB::raw('COALESCE(expired_batches.expired_batches, 0) as expired_batches'),
                DB::raw('COALESCE(near_expiration_batches.near_expiration_batches, 0) as near_expiration_batches'),
                DB::raw('
                    CASE
                        WHEN branch_products.stock <= 0 THEN "OUT_OF_STOCK"
                        WHEN branch_products.stock <= branch_products.min_stock THEN "LOW_STOCK"
                        WHEN COALESCE(expired_batches.expired_batches, 0) > 0 THEN "EXPIRED_BATCHES"
                        WHEN COALESCE(near_expiration_batches.near_expiration_batches, 0) > 0 THEN "NEAR_EXPIRATION"
                        WHEN DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90 THEN "NO_RECENT_MOVEMENT"
                        ELSE "REVIEW"
                    END as recommendation
                '),
            ])
            ->limit(300)
            ->get();
    }

    private function applyCommonFilters($query, array $filters): void
    {
        if (!empty($filters['product_id'])) {
            $query->where('branch_products.id', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('stock_movements.user_id', $filters['user_id']);
        }

        if (!empty($filters['movement_type'])) {
            $query->where('stock_movements.type', $filters['movement_type']);
        }

        if (!empty($filters['movement_reason'])) {
            $query->where('stock_movements.reason', $filters['movement_reason']);
        }
    }

    private function applyPeriod($query, array $filters, string $column = 'created_at'): void
    {
        if (!empty($filters['date_from'])) {
            $query->whereDate($column, '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate($column, '<=', $filters['date_to']);
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            return;
        }

        match ($filters['period'] ?? '30_days') {
            'today' => $query->whereDate($column, today()),
            '7_days' => $query->where($column, '>=', now()->subDays(7)),
            '30_days' => $query->where($column, '>=', now()->subDays(30)),
            '90_days' => $query->where($column, '>=', now()->subDays(90)),
            '6_months' => $query->where($column, '>=', now()->subMonths(6)),
            default => null,
        };
    }
}