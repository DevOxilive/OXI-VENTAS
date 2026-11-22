<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class InventoryIncidentReportService
{
    /**
     * Incidencias de POS que agotaron los lotes reales y conservaron el faltante
     * en una entrada sin lote. El faltante no se pierde: representa una carga pendiente.
     */
    public function rows(Branch $branch, array $filters = []): LengthAwarePaginator
    {
        $query = ProductBatch::query()
            ->select([
                'product_batches.id',
                'product_batches.branch_product_id',
                'product_batches.quantity as pending_quantity',
                'products.name as product',
                'branch_products.stock as current_stock',
                'categories.name as category',
                'product_departments.name as department',
            ])
            ->join('branch_products', 'branch_products.id', '=', 'product_batches.branch_product_id')
            ->join('products', 'products.id', '=', 'branch_products.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('product_departments', 'product_departments.id', '=', 'categories.product_department_id')
            ->where('branch_products.branch_id', $branch->id)
            ->where('product_batches.quantity', '<', 0)
            ->where(function ($query) {
                $query->whereNull('product_batches.lot_number')
                    ->orWhereRaw("TRIM(product_batches.lot_number) = ''");
            })
            ->whereExists($this->incidentMovementsQuery())
            ->selectSub($this->firstIncidentAtQuery(), 'first_incident_at')
            ->selectSub($this->firstIncidentUserQuery(), 'first_incident_user')
            ->selectSub($this->continuedSalesCountQuery(), 'continued_sales_count')
            ->selectSub($this->lastIncidentAtQuery(), 'last_incident_at')
            ->orderBy('first_incident_at')
            ->orderBy('products.name');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('products.name', 'like', "%{$search}%")
                    ->orWhere('branch_products.barcode', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200], true) ? $perPage : 25;

        return $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn ($row) => [
                'id' => (int) $row->id,
                'product' => $row->product,
                'department' => $row->department ?? 'Sin departamento',
                'category' => $row->category ?? 'Sin categoría',
                'pending_quantity' => (float) $row->pending_quantity,
                'current_stock' => (float) $row->current_stock,
                'first_incident_at' => $row->first_incident_at,
                'first_incident_user' => $row->first_incident_user ?? 'Sin usuario',
                'continued_sales_count' => (int) $row->continued_sales_count,
                'last_incident_at' => $row->last_incident_at,
                'status_label' => 'Carga pendiente',
            ]);
    }

    private function incidentMovementsQuery(): Builder
    {
        return DB::table('stock_movement_batches as incident_batches')
            ->join('stock_movements as incident_movements', 'incident_movements.id', '=', 'incident_batches.stock_movement_id')
            ->whereColumn('incident_batches.product_batch_id', 'product_batches.id')
            ->where('incident_movements.type', StockMovement::TYPE_OUT)
            ->where('incident_movements.reason', StockMovement::REASON_SALE)
            ->whereNotNull('incident_movements.sale_id')
            ->where('incident_batches.new_batch_quantity', '<', 0);
    }

    private function firstIncidentAtQuery(): Builder
    {
        return $this->incidentMovementsQuery()
            ->selectRaw('MIN(incident_movements.created_at)');
    }

    private function firstIncidentUserQuery(): Builder
    {
        return DB::table('stock_movement_batches as incident_batches')
            ->join('stock_movements as incident_movements', 'incident_movements.id', '=', 'incident_batches.stock_movement_id')
            ->leftJoin('users as incident_users', 'incident_users.id', '=', 'incident_movements.user_id')
            ->whereColumn('incident_batches.product_batch_id', 'product_batches.id')
            ->where('incident_movements.type', StockMovement::TYPE_OUT)
            ->where('incident_movements.reason', StockMovement::REASON_SALE)
            ->whereNotNull('incident_movements.sale_id')
            ->where('incident_batches.new_batch_quantity', '<', 0)
            ->orderBy('incident_movements.created_at')
            ->orderBy('incident_movements.id')
            ->limit(1)
            ->select('incident_users.name');
    }

    private function continuedSalesCountQuery(): Builder
    {
        return $this->incidentMovementsQuery()
            ->where('incident_batches.previous_batch_quantity', '<', 0)
            ->selectRaw('COUNT(*)');
    }

    private function lastIncidentAtQuery(): Builder
    {
        return $this->incidentMovementsQuery()
            ->selectRaw('MAX(incident_movements.created_at)');
    }
}
