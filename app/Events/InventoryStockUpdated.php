<?php

namespace App\Events;

use App\Models\BranchProduct;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryStockUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BranchProduct $branchProduct
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('inventory');
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    public function broadcastWith(): array
    {
        $this->branchProduct->load([
            'batches' => fn($query) => $query
                ->where('quantity', '>', 0)
                ->orderBy('expiration_date'),

            'movements' => fn($query) => $query
                ->with('user')
                ->latest()
                ->limit(5),
        ]);

        return [
            'branch_product_id' => $this->branchProduct->id,
            'branch_id' => $this->branchProduct->branch_id,
            'product_id' => $this->branchProduct->product_id,
            'stock' => $this->branchProduct->stock,
            'updated_at' => $this->branchProduct->updated_at,

            'batches' => $this->branchProduct->batches,
            'recent_movements' => $this->branchProduct->movements,
        ];
    }
}
