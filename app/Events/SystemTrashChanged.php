<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Announces a global-trash mutation so open administration views stay synchronized. */
class SystemTrashChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $resource,
        public string $action,
        public array $recordIds = [],
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('systems')];
    }

    public function broadcastAs(): string
    {
        return 'system.trash.changed';
    }
}
