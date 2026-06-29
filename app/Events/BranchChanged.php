<?php

namespace App\Events;

use App\Models\Branch;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BranchChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $action,
        public ?int $branchId = null,
        public ?string $slug = null,
    ) {}

    public static function fromBranch(Branch $branch, string $action): self
    {
        return new self($action, $branch->id, $branch->slug);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('systems'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'branch.changed';
    }
}
