<?php

namespace App\Console\Commands;

use App\Services\InventoryTaskProcessor;
use Illuminate\Console\Command;

class WorkInventoryTasks extends Command
{
    protected $signature = 'inventory:work {--limit=500 : Maximum tasks per run} {--max-time=45 : Seconds before stopping between tasks} {--wait : Poll every second until max-time when no tasks are ready}';

    protected $description = 'Process durable inventory notifications and search updates without replaying stock movements.';

    public function handle(InventoryTaskProcessor $processor): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $seconds = filter_var($this->option('max-time'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);

        if ($limit === false || $seconds === false) {
            $this->error('limit debe ser 1–10000 y max-time debe ser 1–3600.');

            return self::INVALID;
        }

        $started = microtime(true);
        $processed = 0;

        while ($processed < $limit && microtime(true) - $started < $seconds) {
            if (! $processor->processNext()) {
                if (! $this->option('wait')) {
                    break;
                }

                usleep(1000000);

                continue;
            }

            $processed++;
        }

        $this->info(sprintf('Tareas atendidas: %d. Duración: %.2f s.', $processed, microtime(true) - $started));

        return self::SUCCESS;
    }
}
