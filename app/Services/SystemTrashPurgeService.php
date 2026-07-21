<?php

namespace App\Services;

use App\Support\TrashRegistry;
use App\Support\TrashRetentionPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SystemTrashPurgeService
{
    private const BATCH_SIZE = 100;

    public function __construct(private readonly SystemAuditService $audit) {}

    public function purgeExpired(?Request $request = null): array
    {
        $summary = [];

        foreach (TrashRetentionPolicy::purgeableResources() as $resource) {
            $deleted = 0;
            $cutoff = now()->subDays(TrashRetentionPolicy::days($resource));

            TrashRegistry::query($resource)
                ->where('deleted_at', '<=', $cutoff)
                ->orderBy('id')
                ->chunkById(self::BATCH_SIZE, function ($models) use (&$deleted) {
                    foreach ($models as $model) {
                        $this->purge($model);
                        $deleted++;
                    }
                });

            if ($deleted > 0) {
                $summary[$resource] = $deleted;
            }
        }

        $this->audit->record('system-trash', 'purge_expired', 'success', $request, [
            'actor_name' => $request?->user() ? null : 'Sistema',
            'observations' => $summary === []
                ? 'La depuración no encontró registros vencidos.'
                : 'Depuración automática de registros vencidos completada.',
            'metadata' => [
                'actor' => 'system',
                'resources' => $summary,
                'retention_days' => array_filter(
                    collect(TrashRetentionPolicy::purgeableResources())
                        ->mapWithKeys(fn (string $resource) => [$resource => TrashRetentionPolicy::days($resource)])
                        ->all()
                ),
            ],
        ]);

        return $summary;
    }

    public function purgeScheduledRecord(string $resource, int $recordId): bool
    {
        if (!TrashRetentionPolicy::isPurgeable($resource)) {
            return false;
        }

        $model = TrashRegistry::query($resource)->find($recordId);
        if (!$model || !$model->deleted_at) {
            return false;
        }

        $expiresAt = TrashRetentionPolicy::expiresAt($resource, $model->deleted_at);
        if ($expiresAt?->isFuture()) {
            return false;
        }

        $this->purge($model);

        return true;
    }

    public function purge(Model $model): void
    {
        if (method_exists($model, 'permissions')) $model->permissions()->detach();
        if (method_exists($model, 'branches')) $model->branches()->detach();
        if (method_exists($model, 'assignedPhysicalCounts')) $model->assignedPhysicalCounts()->detach();

        $model->forceDelete();
    }
}
