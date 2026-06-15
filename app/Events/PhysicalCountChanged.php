<?php

namespace App\Events;

use App\Models\PhysicalCount;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PhysicalCountChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $physicalCountId;
    public string $action;

    public function __construct(PhysicalCount $physicalCount, string $action = 'updated')
    {
        $this->physicalCountId = $physicalCount->id;
        $this->action = $action;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('audits'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PhysicalCountChanged';
    }
}