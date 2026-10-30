<?php

namespace App\Services;

use App\Models\BranchProduct;
use App\Models\InventoryTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class InventoryOutbox
{
    /** Persist on the caller's database connection, before its transaction commits. */
    public function record(string $type, array $payload): void
    {
        if (! in_array($type, ['stock.updated', 'product.changed', 'activity', 'product.search', 'system.audit.changed', 'system.trash.changed', 'trash.purge'], true)) {
            throw new InvalidArgumentException('Unsupported inventory task type.');
        }

        $actor = Auth::user();

        InventoryTask::query()->create([
            'type' => $type,
            'payload' => array_merge($payload, [
                'actor' => [
                    'id' => $actor?->id,
                    'name' => $actor?->name ?? 'Sistema',
                    'email' => $actor?->email ?? 'sistema',
                ],
                'socket_id' => request()->header('X-Socket-ID'),
            ]),
            'status' => InventoryTask::STATUS_PENDING,
            'attempts' => 0,
            'available_at' => $type === 'trash.purge' ? Carbon::parse($payload['purge_after']) : now(),
        ]);
    }

    public function stockChanged(BranchProduct $bp, string $actionLabel, string $entityLabel, string $action): void
    {
        $this->record('stock.updated', ['branch_product_id' => (int) $bp->id]);
        $this->recordActivity($actionLabel, $entityLabel, $bp->product?->name, $action);
    }

    public function productChanged(string $action, int $productId, array $branchIds, string $actionLabel, string $entityLabel, ?string $entityName): void
    {
        $this->record('product.changed', [
            'action' => $action,
            'product_id' => $productId,
            'branch_ids' => array_values(array_unique(array_map('intval', $branchIds))),
        ]);
        $this->recordActivity($actionLabel, $entityLabel, $entityName, $action);
    }

    public function searchProduct(int $id): void
    {
        $this->record('product.search', ['product_id' => $id]);
    }

    private function recordActivity(string $actionLabel, string $entityLabel, ?string $entityName, string $action): void
    {
        $this->record('activity', [
            'action_label' => $actionLabel,
            'entity_label' => $entityLabel,
            'entity_name' => $entityName,
            'module' => 'Inventario',
            'action' => $action,
        ]);
    }
}
