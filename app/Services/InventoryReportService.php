<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryReportService
{
    public function rows(Branch $branch, array $filters)
    {
        if (in_array($filters['status'] ?? null, ['low_stock', 'out_of_stock'], true)) {
            return $this->attentionProducts($branch, $filters);
        }

        return match ($filters['report'] ?? 'dashboard') {
            'movements' => $this->movements($branch, $filters),
            'expirations' => $this->inventoryLots($branch, $filters),
            'rotation' => $this->rotation($branch, $filters),
            'attention' => $this->attentionProducts($branch, $filters),
            default => $this->inventoryLots($branch, $filters),
        };
    }

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

        return [
            'total_products' => (int) ($productSummary->total_products ?? 0),
            'total_stock' => (float) ($productSummary->total_stock ?? 0),
            'low_stock' => (int) ($productSummary->low_stock ?? 0),
            'out_of_stock' => (int) ($productSummary->out_of_stock ?? 0),
            'expired_batches' => (int) ($batchSummary->expired_batches ?? 0),
            'near_expiration_batches' => (int) ($batchSummary->near_expiration_batches ?? 0),
            'attention_products' => $this->attentionProducts($branch)->count(),
        ];
    }

    public function inventoryLots(Branch $branch, array $filters)
    {
        $lastInSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('MAX(created_at) as last_entry_at'))
            ->where('type', StockMovement::TYPE_IN)
            ->groupBy('branch_product_id');

        $query = ProductBatch::query()
            ->join('branch_products', 'branch_products.id', '=', 'product_batches.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($lastInSubquery, 'last_entries', function ($join) {
                $join->on('last_entries.branch_product_id', '=', 'branch_products.id');
            })
            ->where('branch_products.branch_id', $branch->id)
            ->select([
                'product_batches.id',
                'products.name as product',
                'categories.name as category',
                'product_batches.lot_number',
                'product_batches.initial_quantity',
                'product_batches.quantity',
                'product_batches.expiration_date',
                DB::raw('DATE(product_batches.received_at) as received_at'),
                DB::raw('DATE(last_entries.last_entry_at) as last_entry_at'),
                DB::raw('branch_products.stock as current_stock'),
                'branch_products.min_stock',
                DB::raw('DATEDIFF(product_batches.expiration_date, CURDATE()) as days'),
                'products.cost as unit_cost',
                DB::raw('(product_batches.quantity * products.cost) as estimated_loss'),
                DB::raw('NULL as movement_date'),
                DB::raw('NULL as user'),
                DB::raw('NULL as movement_type'),
                DB::raw('NULL as movement_reason'),
                DB::raw('NULL as movement_reason_label'),
                DB::raw('NULL as previous_stock'),
                DB::raw('NULL as new_stock'),
                DB::raw('NULL as notes'),
                DB::raw('
                    CASE
                        WHEN product_batches.expiration_date < CURDATE() THEN "Caducado"
                        WHEN product_batches.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN "Proximo a caducar"
                        ELSE "Vigente"
                    END as status_label
                '),
            ])
            ->orderByRaw('product_batches.expiration_date IS NULL')
            ->orderBy('product_batches.expiration_date')
            ->orderBy('products.name');

        $this->applyProductFilters($query, $filters);
        $this->applySearchFilter($query, $filters, [
            'products.name',
            'categories.name',
            'product_batches.lot_number',
        ]);
        $this->applyBatchExpirationPeriod($query, $filters);

        match ($filters['status'] ?? null) {
            'expired' => $query->whereDate('product_batches.expiration_date', '<', today()),
            'near_expiration' => $this->applyNearExpirationFilter($query, $filters),
            default => null,
        };

        return $query->limit(300)->get();
    }

    public function movements(Branch $branch, array $filters)
    {
        $query = StockMovement::query()
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->leftJoin('stock_movement_batches', 'stock_movement_batches.stock_movement_id', '=', 'stock_movements.id')
            ->leftJoin('product_batches', 'product_batches.id', '=', 'stock_movement_batches.product_batch_id')
            ->where('branch_products.branch_id', $branch->id)
            ->select([
                'stock_movements.id',
                'products.name as product',
                'categories.name as category',
                DB::raw('GROUP_CONCAT(DISTINCT product_batches.lot_number ORDER BY product_batches.lot_number SEPARATOR ", ") as lot_number'),
                DB::raw('NULL as initial_quantity'),
                'stock_movements.quantity',
                DB::raw('MIN(DATE(product_batches.expiration_date)) as expiration_date'),
                DB::raw('NULL as received_at'),
                DB::raw('NULL as last_entry_at'),
                DB::raw('branch_products.stock as current_stock'),
                'branch_products.min_stock',
                DB::raw('DATE(stock_movements.created_at) as movement_date'),
                DB::raw('NULL as days'),
                'products.cost as unit_cost',
                DB::raw('(stock_movements.quantity * products.cost) as estimated_loss'),
                DB::raw('
                    CASE stock_movements.type
                        WHEN "IN" THEN "Entrada"
                        WHEN "OUT" THEN "Salida"
                        WHEN "ADJUSTMENT" THEN "Ajuste"
                        ELSE stock_movements.type
                    END as status_label
                '),
                'stock_movements.type as movement_type',
                'stock_movements.reason as movement_reason',
                DB::raw('
                    CASE stock_movements.reason
                        WHEN "PURCHASE" THEN "Compra"
                        WHEN "SALE" THEN "Venta"
                        WHEN "DAMAGED" THEN "Danado"
                        WHEN "EXPIRED" THEN "Caducado"
                        WHEN "INVENTORY_DIFFERENCE" THEN "Ajuste manual"
                        ELSE stock_movements.reason
                    END as movement_reason_label
                '),
                'stock_movements.previous_stock',
                'stock_movements.new_stock',
                'users.name as user',
                'stock_movements.notes',
            ])
            ->groupBy([
                'stock_movements.id',
                'products.name',
                'categories.name',
                'stock_movements.quantity',
                'branch_products.stock',
                'branch_products.min_stock',
                'products.cost',
                'stock_movements.type',
                'stock_movements.reason',
                'stock_movements.previous_stock',
                'stock_movements.new_stock',
                'users.name',
                'stock_movements.notes',
                'stock_movements.created_at',
            ])
            ->latest('stock_movements.created_at');

        $this->applyPeriod($query, $filters, 'stock_movements.created_at');
        $this->applyProductFilters($query, $filters);
        $this->applySearchFilter($query, $filters, [
            'products.name',
            'categories.name',
            'product_batches.lot_number',
            'users.name',
            'stock_movements.reason',
            'stock_movements.notes',
        ]);

        if (!empty($filters['user_id'])) {
            $query->where('stock_movements.user_id', $filters['user_id']);
        }

        if (!empty($filters['movement_type'])) {
            $query->where('stock_movements.type', $filters['movement_type']);
        }

        if (!empty($filters['movement_reason'])) {
            $query->where('stock_movements.reason', $filters['movement_reason']);
        }

        return $query->limit(300)->get();
    }

    public function expirations(Branch $branch, array $filters)
    {
        return $this->inventoryLots($branch, $filters);
    }

    public function rotation(Branch $branch, array $filters)
    {
        $periodMovementSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('SUM(quantity) as total_out'))
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $this->applyPeriod($periodMovementSubquery, $filters);

        $lastMovementSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('MAX(created_at) as last_out_at'))
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $lastInSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('MAX(created_at) as last_entry_at'))
            ->where('type', StockMovement::TYPE_IN)
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
            ->leftJoinSub($lastInSubquery, 'last_entries', function ($join) {
                $join->on('last_entries.branch_product_id', '=', 'branch_products.id');
            })
            ->where('branch_products.branch_id', $branch->id)
            ->select([
                'branch_products.id',
                'products.name as product',
                'categories.name as category',
                DB::raw('NULL as lot_number'),
                DB::raw('NULL as initial_quantity'),
                DB::raw('branch_products.stock as quantity'),
                DB::raw('DATE(last_movements.last_out_at) as expiration_date'),
                DB::raw('NULL as received_at'),
                DB::raw('DATE(last_entries.last_entry_at) as last_entry_at'),
                DB::raw('branch_products.stock as current_stock'),
                'branch_products.min_stock',
                DB::raw('DATEDIFF(CURDATE(), last_movements.last_out_at) as days'),
                'products.cost as unit_cost',
                DB::raw('(branch_products.stock * products.cost) as estimated_loss'),
                DB::raw('COALESCE(period_movements.total_out, 0) as total_out'),
                DB::raw('NULL as movement_date'),
                DB::raw('NULL as user'),
                DB::raw('NULL as movement_type'),
                DB::raw('NULL as movement_reason'),
                DB::raw('NULL as movement_reason_label'),
                DB::raw('NULL as previous_stock'),
                DB::raw('NULL as new_stock'),
                DB::raw('NULL as notes'),
                DB::raw('
                    CASE
                        WHEN COALESCE(period_movements.total_out, 0) <= 0 THEN "Sin movimiento"
                        WHEN DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90 THEN "Rotacion baja"
                        WHEN COALESCE(period_movements.total_out, 0) >= 30 THEN "Rotacion alta"
                        WHEN COALESCE(period_movements.total_out, 0) >= 10 THEN "Rotacion media"
                        ELSE "Rotacion baja"
                    END as status_label
                '),
            ]);

        $this->applyProductFilters($query, $filters);
        $this->applySearchFilter($query, $filters, [
            'products.name',
            'categories.name',
        ]);

        return $query->limit(300)->get();
    }

    public function attentionProducts(Branch $branch, array $filters = [])
    {
        $lastMovementSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('MAX(created_at) as last_out_at'))
            ->where('type', StockMovement::TYPE_OUT)
            ->groupBy('branch_product_id');

        $lastInSubquery = StockMovement::query()
            ->select('branch_product_id', DB::raw('MAX(created_at) as last_entry_at'))
            ->where('type', StockMovement::TYPE_IN)
            ->groupBy('branch_product_id');

        $expiredBatchSubquery = ProductBatch::query()
            ->select('branch_product_id', DB::raw('COUNT(*) as expired_batches'))
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', today())
            ->groupBy('branch_product_id');

        $nearExpirationBatchSubquery = ProductBatch::query()
            ->select('branch_product_id', DB::raw('COUNT(*) as near_expiration_batches'))
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', today())
            ->whereDate('expiration_date', '<=', now()->addDays(30))
            ->groupBy('branch_product_id');

        $query = BranchProduct::query()
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($lastMovementSubquery, 'last_movements', function ($join) {
                $join->on('last_movements.branch_product_id', '=', 'branch_products.id');
            })
            ->leftJoinSub($lastInSubquery, 'last_entries', function ($join) {
                $join->on('last_entries.branch_product_id', '=', 'branch_products.id');
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
                'branch_products.id',
                'products.name as product',
                'categories.name as category',
                DB::raw('NULL as lot_number'),
                DB::raw('NULL as initial_quantity'),
                DB::raw('branch_products.stock as quantity'),
                DB::raw('NULL as expiration_date'),
                DB::raw('NULL as received_at'),
                DB::raw('DATE(last_entries.last_entry_at) as last_entry_at'),
                DB::raw('branch_products.stock as current_stock'),
                'branch_products.min_stock',
                DB::raw('DATEDIFF(CURDATE(), last_movements.last_out_at) as days'),
                'products.cost as unit_cost',
                DB::raw('(branch_products.stock * products.cost) as estimated_loss'),
                DB::raw('COALESCE(expired_batches.expired_batches, 0) as expired_batches'),
                DB::raw('COALESCE(near_expiration_batches.near_expiration_batches, 0) as near_expiration_batches'),
                DB::raw('NULL as movement_date'),
                DB::raw('NULL as user'),
                DB::raw('NULL as movement_type'),
                DB::raw('NULL as movement_reason'),
                DB::raw('NULL as movement_reason_label'),
                DB::raw('NULL as previous_stock'),
                DB::raw('NULL as new_stock'),
                DB::raw('NULL as notes'),
                DB::raw('
                    CASE
                        WHEN branch_products.stock <= 0 THEN "Agotado"
                        WHEN branch_products.stock <= branch_products.min_stock THEN "Stock bajo"
                        WHEN COALESCE(expired_batches.expired_batches, 0) > 0 THEN "Con lotes caducados"
                        WHEN COALESCE(near_expiration_batches.near_expiration_batches, 0) > 0 THEN "Con lotes por vencer"
                        WHEN DATEDIFF(CURDATE(), last_movements.last_out_at) >= 90 THEN "Sin movimiento reciente"
                        ELSE "Revisar"
                    END as status_label
                '),
            ]);

        $this->applyProductFilters($query, $filters);
        $this->applySearchFilter($query, $filters, [
            'products.name',
            'categories.name',
        ]);

        match ($filters['status'] ?? null) {
            'out_of_stock' => $query->where('branch_products.stock', '<=', 0),
            'low_stock' => $query
                ->where('branch_products.stock', '>', 0)
                ->whereColumn('branch_products.stock', '<=', 'branch_products.min_stock'),
            'expired' => $query->whereRaw('COALESCE(expired_batches.expired_batches, 0) > 0'),
            'near_expiration' => $query->whereRaw('COALESCE(near_expiration_batches.near_expiration_batches, 0) > 0'),
            default => null,
        };

        return $query->limit(300)->get();
    }

    private function applyProductFilters($query, array $filters): void
    {
        if (!empty($filters['product_id'])) {
            $query->where('branch_products.id', $filters['product_id']);
        }

        if (!empty($filters['category_id'])) {
            $query->where('products.category_id', $filters['category_id']);
        }
    }

    private function applySearchFilter($query, array $filters, array $columns): void
    {
        if (empty($filters['search'])) {
            return;
        }

        $query->where(function ($searchQuery) use ($filters, $columns) {
            foreach ($columns as $column) {
                $searchQuery->orWhere($column, 'like', '%' . $filters['search'] . '%');
            }
        });
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

        match ($filters['period'] ?? null) {
            'today' => $query->whereDate($column, today()),
            '7_days' => $query->where($column, '>=', now()->subDays(7)),
            '30_days' => $query->where($column, '>=', now()->subDays(30)),
            '90_days' => $query->where($column, '>=', now()->subDays(90)),
            '6_months' => $query->where($column, '>=', now()->subMonths(6)),
            default => null,
        };
    }

    private function applyBatchExpirationPeriod($query, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $query->whereDate('product_batches.expiration_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('product_batches.expiration_date', '<=', $filters['date_to']);
        }
    }

    private function applyNearExpirationFilter($query, array $filters): void
    {
        $query->whereDate('product_batches.expiration_date', '>=', today());

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            return;
        }

        $query->whereDate('product_batches.expiration_date', '<=', now()->addDays(30));
    }
}
