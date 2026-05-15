<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmployeeChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public ?int $employeeId = null
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('systems'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'employee.changed';
    }
}
