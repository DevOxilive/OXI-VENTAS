<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Announces a committed audit entry to the System Administration screens. */
class SystemAuditChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $module,
        public string $action,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('systems')];
    }

    public function broadcastAs(): string
    {
        return 'system.audit.changed';
    }
}
