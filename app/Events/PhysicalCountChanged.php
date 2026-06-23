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
    public array $physicalCount;
    public string $action;

    public function __construct(PhysicalCount $physicalCount, string $action = 'updated')
    {
        $this->physicalCountId = $physicalCount->id;
        $this->physicalCount = [
            'id' => $physicalCount->id,
            'branch_id' => $physicalCount->branch_id,
            'status' => $physicalCount->status,
        ];
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
