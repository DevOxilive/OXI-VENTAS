<?php

namespace App\Observers;

use App\Events\SystemTrashChanged;
use App\Jobs\PurgeTrashRecord;
use App\Models\SystemAudit;
use App\Services\SystemAuditService;
use App\Support\TrashRegistry;
use App\Support\TrashRetentionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

class SystemAuditObserver
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function created(Model $model): void
    {
        $this->record($model, 'create');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'update', $this->safeChanges($model->getChanges()));
    }

    public function deleted(Model $model): void
    {
        $this->record($model, $model->isForceDeleting() ? 'force_delete' : 'delete');
    }

    public function restored(Model $model): void
    {
        $this->record($model, 'restore');
    }

    private function record(Model $model, string $action, array $metadata = []): void
    {
        if ($model instanceof SystemAudit || !app()->bound('request')) {
            return;
        }

        $resource = TrashRegistry::keyForModel($model);

        if ($resource && $action === 'delete') {
            $expiresAt = TrashRetentionPolicy::isPurgeable($resource)
                ? TrashRetentionPolicy::expiresAt($resource, $model->deleted_at)
                : null;

            $metadata['trash'] = [
                'resource' => $resource,
                'retention_days' => TrashRetentionPolicy::days($resource),
                'expires_at' => $expiresAt?->toIso8601String(),
                'restore_mode' => 'complete',
            ];
        }

        $this->audit->record(
            Str::kebab(class_basename($model)),
            $action,
            'success',
            request(),
            [
                'actor_name' => app()->runningInConsole() ? 'Sistema' : null,
                'record_type' => $model::class,
                'record_id' => $model->getKey(),
                'record_label' => TrashRegistry::recordLabel($model),
                'metadata' => $metadata,
            ],
        );

        if ($resource && $action === 'delete' && TrashRetentionPolicy::isPurgeable($resource)) {
            $expiresAt = TrashRetentionPolicy::expiresAt($resource, $model->deleted_at);

            if ($expiresAt) {
                PurgeTrashRecord::dispatch($resource, $model->getKey())->delay($expiresAt);
            }
        }

        if ($resource && in_array($action, ['delete', 'restore', 'force_delete'], true)) {
            try {
                broadcast(new SystemTrashChanged($resource, $action, [$model->getKey()]))->toOthers();
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function safeChanges(array $changes): array
    {
        return collect($changes)
            ->except(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])
            ->all();
    }
}
