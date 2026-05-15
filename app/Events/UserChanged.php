<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public ?int $userId = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('systems'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'user.changed';
    }
}
