<?php

namespace App\Jobs;

use App\Services\SystemTrashPurgeService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PurgeTrashRecord implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $resource,
        public readonly int $recordId,
    ) {}

    /**
     * The service verifies the record is still deleted and has actually reached
     * its retention date. A restored or re-deleted record is never purged early.
     */
    public function handle(SystemTrashPurgeService $purgeService): void
    {
        $purgeService->purgeScheduledRecord($this->resource, $this->recordId);
    }
}
