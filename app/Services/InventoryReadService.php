<?php

namespace App\Services;

use App\Models\BranchProduct;
use App\Support\LocalDateTime;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryReadService
{
    public function movements(BranchProduct $branchProduct, Request $request): array
    {
        $filters = $request->validate([
            'movements_page' => ['sometimes', 'integer', 'min:1'],
            'movements_per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'movement_group' => ['nullable', Rule::in([
                'all', 'purchases', 'sales', 'returns', 'damaged', 'expired', 'audits', 'adjustments', 'others',
            ])],
            'movement_user_id' => ['nullable', 'integer', 'min:0'],
            'movement_date_from' => ['nullable', 'date_format:Y-m-d'],
            'movement_date_to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $group = $this->movementGroupExpression();
        $base = $branchProduct->movements();
        $filtered = clone $base;
        if (filled($filters['movement_group'] ?? null) && $filters['movement_group'] !== 'all') {
            $filtered->whereRaw("({$group}) = ?", [$filters['movement_group']]);
        }
        if (isset($filters['movement_user_id'])) {
            if ((int) $filters['movement_user_id'] === 0) {
                $filtered->whereDoesntHave('user');
            } else {
                $filtered->where('user_id', $filters['movement_user_id']);
            }
        }
        if (filled($filters['movement_date_from'] ?? null)) {
            $filtered->where('stock_movements.created_at', '>=', LocalDateTime::startOfDay($filters['movement_date_from']));
        }
        if (filled($filters['movement_date_to'] ?? null)) {
            $filtered->where('stock_movements.created_at', '<=', LocalDateTime::endOfDay($filters['movement_date_to']));
        }

        $summary = (clone $filtered)->selectRaw("{$group} as movement_group, COUNT(*) as movement_count")
            ->groupByRaw($group)->get()
            ->map(fn ($row) => ['key' => $row->movement_group, 'count' => (int) $row->movement_count])->values();
        $filteredTotal = $summary->sum('count');
        $perPage = (int) ($filters['movements_per_page'] ?? 25);
        $page = (int) ($filters['movements_page'] ?? 1);
        $movements = $filtered
            ->select(['stock_movements.id', 'branch_product_id', 'user_id', 'type', 'reason', 'quantity', 'previous_stock', 'new_stock', 'notes', 'stock_movements.created_at'])
            ->with(['user:id,name', 'batches:id,stock_movement_id,product_batch_id,quantity', 'batches.productBatch:id,lot_number'])
            ->orderByDesc('stock_movements.created_at')->orderByDesc('stock_movements.id')
            ->paginate($perPage, ['*'], 'movements_page', $page, $filteredTotal);

        $users = (clone $base)->leftJoin('users', function ($join) {
            $join->on('users.id', '=', 'stock_movements.user_id')->whereNull('users.deleted_at');
        })
            ->selectRaw("COALESCE(users.id, 0) as id, COALESCE(users.name, 'Usuario no disponible') as name")
            ->groupBy('users.id', 'users.name')->orderBy('name')->get()
            ->map(fn ($user) => ['id' => (int) $user->id, 'name' => $user->name])->values();

        return [
            'movements' => $movements->items(),
            'movements_total' => (clone $base)->count(),
            'movements_users' => $users,
            'movements_summary' => $summary,
            'movements_pagination' => [
                'current_page' => $movements->currentPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'last_page' => $movements->lastPage(),
                'has_more' => $movements->hasMorePages(),
                'next_page' => $movements->hasMorePages() ? $movements->currentPage() + 1 : null,
            ],
        ];
    }

    private function movementGroupExpression(): string
    {
        return "CASE
            WHEN reason = 'PURCHASE' THEN 'purchases'
            WHEN reason = 'SALE' THEN 'sales'
            WHEN reason = 'RETURN' THEN 'returns'
            WHEN reason = 'DAMAGED' THEN 'damaged'
            WHEN reason = 'EXPIRED' THEN 'expired'
            WHEN reason = 'INVENTORY_DIFFERENCE' AND (
                LOWER(COALESCE(notes, '')) LIKE '%auditoria%'
                OR LOWER(COALESCE(notes, '')) LIKE '%auditoría%'
                OR LOWER(COALESCE(notes, '')) LIKE '%conteo fisico%'
                OR LOWER(COALESCE(notes, '')) LIKE '%conteo físico%'
            ) THEN 'audits'
            WHEN type = 'ADJUSTMENT' THEN 'adjustments'
            ELSE 'others' END";
    }
}
