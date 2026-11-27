<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Keeps every open purchase-review screen synchronized. */
class PurchaseWorkflowChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public ?int $purchaseOrderId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('systems')];
    }

    public function broadcastAs(): string
    {
        return 'purchase-workflow.changed';
    }
}
