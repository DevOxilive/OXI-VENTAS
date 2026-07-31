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
    public ?int $actorUserId;
    public array $details;

    public function __construct(PhysicalCount $physicalCount, string $action = 'updated', array $details = [])
    {
        $physicalCount->loadMissing(['branch', 'creator', 'participants:id,name', 'currentRound']);

        $this->physicalCountId = $physicalCount->id;
        $this->physicalCount = [
            'id' => $physicalCount->id,
            'branch_id' => $physicalCount->branch_id,
            'branch' => $physicalCount->branch,
            'name' => $physicalCount->name,
            'folio' => $physicalCount->folio,
            'status' => $physicalCount->status,
            'current_round_number' => $physicalCount->currentRound?->round_number ?? 1,
            'participants' => $physicalCount->participants
                ->map(fn ($participant) => [
                    'id' => $participant->id,
                    'name' => $participant->name,
                ])
                ->values()
                ->all(),
            'participant_ids' => $physicalCount->participants->pluck('id')->values()->all(),
        ];
        $this->action = $action;
        $this->actorUserId = auth()->id();
        $this->details = $details;
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
