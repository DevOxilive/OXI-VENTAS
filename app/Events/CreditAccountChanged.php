<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CreditAccountChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public ?int $accountId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('systems')];
    }

    public function broadcastAs(): string
    {
        return 'credit-account.changed';
    }
}
