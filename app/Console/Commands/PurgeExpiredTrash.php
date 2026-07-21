<?php

namespace App\Console\Commands;

use App\Services\SystemTrashPurgeService;
use Illuminate\Console\Command;

class PurgeExpiredTrash extends Command
{
    protected $signature = 'system:purge-expired-trash';
    protected $description = 'Permanently removes only global-trash records past their configured retention period.';

    public function handle(SystemTrashPurgeService $purgeService): int
    {
        $summary = $purgeService->purgeExpired();
        $total = array_sum($summary);

        $this->info("Depuración finalizada: {$total} registros eliminados.");

        return self::SUCCESS;
    }
}
