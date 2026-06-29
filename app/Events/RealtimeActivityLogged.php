<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
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

    public function __construct(
        string $message,
        string $module,
        string $action,
        ?string $entity = null,
    ) {
        $actor = Auth::user();

        $this->actorName = $actor?->name ?? 'Sistema';
        $this->actorEmail = $actor?->email ?? 'sistema';
        $this->message = $message;
        $this->module = $module;
        $this->action = $action;
        $this->entity = $entity;
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
        return [
            new Channel('activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'realtime.activity';
    }
}
