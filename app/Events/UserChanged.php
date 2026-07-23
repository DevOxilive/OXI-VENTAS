<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $userId;
    public string $action;
    public ?string $role;
    public array $permissions;

    public function __construct(User $user, string $action = 'updated')
    {
        $user->loadMissing(['role.permissions', 'permissions']);

        $this->userId = $user->id;
        $this->action = $action;
        $this->role = $user->role?->name;

        $this->permissions = $user->all_permissions
            ->pluck('name')
            ->values()
            ->toArray();
    }

    public function broadcastOn(): array
    {
        return [
            new Channel('systems'),
            new PrivateChannel('users.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserChanged';
    }
}
