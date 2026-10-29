<?php

namespace App\Console\Commands;

use App\Models\InventoryTask;
use App\Services\InventoryTaskProcessor;
use Illuminate\Console\Command;

class InspectInventoryTasks extends Command
{
    protected $signature = 'inventory:pending {--retry= : Retry one failed task ID} {--limit=25 : Maximum rows shown}';

    protected $description = 'Inspect inventory work and retry a failed task without exposing user or product payloads.';

    public function handle(InventoryTaskProcessor $processor): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);

        if ($limit === false) {
            $this->error('limit debe ser 1–500.');

            return self::INVALID;
        }

        if ($this->option('retry') !== null) {
            $id = filter_var($this->option('retry'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

            if ($id === false || ! $processor->retryFailed($id)) {
                $this->error('La tarea no existe o no está fallida. No se modificó ningún pendiente activo.');

                return self::FAILURE;
            }

            $this->info('Tarea '.$id.' preparada para reintento.');
        }

        $counts = InventoryTask::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $this->table(['Estado', 'Total'], $counts->map(fn ($total, $status) => [$status, $total])->values()->all());
        $ready = InventoryTask::query()->where('status', InventoryTask::STATUS_PENDING)->where('available_at', '<=', now());
        $this->info('Disponibles ahora: '.$ready->count().'. Más antigua: '.($ready->min('available_at') ?? 'ninguna').'.');

        $tasks = InventoryTask::query()->where('status', '!=', InventoryTask::STATUS_COMPLETED)
            ->orderByRaw("CASE WHEN status = 'failed' THEN 0 WHEN status = 'processing' THEN 1 WHEN available_at <= ? THEN 2 ELSE 3 END", [now()])
            ->orderBy('id')->limit($limit)->get(['id', 'type', 'status', 'attempts', 'available_at', 'locked_at', 'last_error']);
        $this->table(['ID', 'Tipo', 'Estado', 'Intentos', 'Disponible', 'Reservada', 'Error'], $tasks->map(fn ($task) => [
            $task->id,
            $task->type,
            $task->status,
            $task->attempts,
            $task->available_at?->toDateTimeString(),
            $task->locked_at?->toDateTimeString(),
            $task->last_error,
        ])->all());

        return self::SUCCESS;
    }
}
