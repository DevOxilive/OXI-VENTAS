<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public int $productId,
        public array $branchIds = [],
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('inventory.products'),
        ];

        foreach ($this->branchIds as $branchId) {
            $channels[] = new Channel('inventory.branch.' . $branchId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'product.changed';
    }
}
