<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/** The original actor is captured when saving, never inferred from the worker. */
class InventoryActivityLogged implements ShouldBroadcastNow
{
    public function __construct(
        public string $actorName,
        public string $actorEmail,
        public string $message,
        public string $module,
        public string $action,
        public ?string $entity,
        private array $recipientIds,
    ) {}

    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('users.'.$id), $this->recipientIds);
    }

    public function broadcastAs(): string
    {
        return 'realtime.activity';
    }

    public function broadcastWith(): array
    {
        return [
            'actorName' => $this->actorName,
            'actorEmail' => $this->actorEmail,
            'message' => $this->message,
            'module' => $this->module,
            'action' => $this->action,
            'entity' => $this->entity,
        ];
    }
}
