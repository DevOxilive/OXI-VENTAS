<?php

namespace App\Events;

use App\Models\BranchProduct;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InventoryStockUpdated implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public BranchProduct $branchProduct
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel(
            'inventory.branch.' . $this->branchProduct->branch_id
        );
    }

    public function broadcastAs(): string
    {
        return 'stock.updated';
    }

    public function broadcastWith(): array
    {
        $this->branchProduct->refresh();

        return [
            'branch_product_id' => $this->branchProduct->id,
            'branch_id' => $this->branchProduct->branch_id,
            'product_id' => $this->branchProduct->product_id,
            'stock' => (float) $this->branchProduct->stock,
            'updated_at' => optional($this->branchProduct->updated_at)->toISOString(),
        ];
    }
}
