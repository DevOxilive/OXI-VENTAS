<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class RealtimeActivityLogged implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public string $actorName;
    public string $actorEmail;
    public string $message;
    public string $module;
    public string $action;
    public ?string $entity;
    /** @var array<int, int> */
    private array $recipientIds;

    public function __construct(
        string $message,
        string $module,
        string $action,
        ?string $entity = null,
        array $additionalRecipientIds = [],
        bool $includeDefaultRecipients = true,
    ) {
        $actor = Auth::user();

        $this->actorName = $actor?->name ?? 'Sistema';
        $this->actorEmail = $actor?->email ?? 'sistema';
        $this->message = $message;
        $this->module = $module;
        $this->action = $action;
        $this->entity = $entity;
        $this->recipientIds = collect($includeDefaultRecipients
            ? $this->resolveRecipientIds($actor?->id)
            : [])
            ->merge($additionalRecipientIds)
            ->filter()
            ->map(fn ($userId) => (int) $userId)
            ->unique()
            ->values()
            ->all();
    }

    public static function message(
        string $actionLabel,
        string $entityLabel,
        ?string $entityName,
        string $module,
        string $action,
    ): self {
        $actorEmail = Auth::user()?->email ?? 'sistema';
        $suffix = filled($entityName) ? ": {$entityName}" : '';

        return new self(
            "El usuario {$actorEmail} {$actionLabel} {$entityLabel}{$suffix}",
            $module,
            $action,
            $entityName,
        );
    }

    public function broadcastOn(): array
    {
        return collect($this->recipientIds)
            ->map(fn (int $userId) => new PrivateChannel('users.' . $userId))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'realtime.activity';
    }

    /**
     * Activity alerts are intentionally limited to the actor and the roles
     * responsible for system supervision. Authorization is then enforced by
     * the private users.{id} channel itself.
     *
     * @return array<int, int>
     */
    private function resolveRecipientIds(?int $actorId): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($actorId): void {
                if ($actorId) {
                    $query->whereKey($actorId)->orWhereHas('role', fn ($roleQuery) =>
                        $roleQuery->whereIn('name', ['Administrador', 'Super Administrador'])
                    );

                    return;
                }

                $query->whereHas('role', fn ($roleQuery) =>
                    $roleQuery->whereIn('name', ['Administrador', 'Super Administrador'])
                );
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
