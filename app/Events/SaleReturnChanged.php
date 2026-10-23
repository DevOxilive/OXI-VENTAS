<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SaleReturnChanged implements ShouldBroadcastNow, ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public int $saleId,
        public int $cancellationId,
        public int $branchId,
        public float $amount,
        public string $applicationType,
        public string $saleStatus,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('sales.branch.'.$this->branchId),
            new Channel('systems'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sale-return.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'sale_id' => $this->saleId,
            'sale_cancellation_id' => $this->cancellationId,
            'branch_id' => $this->branchId,
            'amount' => $this->amount,
            'application_type' => $this->applicationType,
            'sale_status' => $this->saleStatus,
        ];
    }
}
