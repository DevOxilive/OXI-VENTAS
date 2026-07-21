<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceChanged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /** @var array<int, int> */
    private array $recipientIds;

    public function __construct(public int $recordId, public string $action, ?int $actorId = null)
    {
        $this->recipientIds = $this->recipients($actorId);
    }

    public function broadcastOn(): array
    {
        return collect($this->recipientIds)
            ->map(fn (int $userId) => new PrivateChannel('users.' . $userId))
            ->all();
    }

    public function broadcastAs(): string { return 'attendance.changed'; }

    /** @return array<int, int> */
    private function recipients(?int $actorId): array
    {
        return User::query()->where('is_active', true)->get()
            ->filter(fn (User $user) => $user->id === $actorId || $user->hasPermission('attendance.view') || $user->hasPermission('attendance.manage'))
            ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }
}
