<?php

namespace App\Services;

use App\Events\BranchChanged;
use App\Events\EmployeeChanged;
use App\Events\ProductChanged;
use App\Events\UserChanged;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Product;
use App\Models\User;
use App\Support\TrashRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Throwable;

class SystemRecoveryService
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function restoreCompletely(string $resource, int $id, Request $request): Model
    {
        $model = TrashRegistry::find($resource, $id);
        $this->restoreModel($model);

        $this->log($request, $resource, $model, $id);
        $this->broadcastRestoredModel($model);

        return $model;
    }

    public function restoreMany(string $resource, array $ids, Request $request): int
    {
        $models = TrashRegistry::query($resource)->whereKey($ids)->get();

        DB::transaction(fn () => $models->each(fn (Model $model) => $this->restoreModel($model)));
        $models->each(fn (Model $model) => $this->broadcastRestoredModel($model));

        $this->audit->record('system-trash', 'restore_many', 'success', $request, [
            'observations' => 'Restauración múltiple de ' . TrashRegistry::label($resource),
            'metadata' => ['resource' => $resource, 'ids' => $models->modelKeys(), 'restore_mode' => 'complete'],
        ]);

        return $models->count();
    }

    public function restoreAll(string $resource, Request $request): int
    {
        $models = TrashRegistry::query($resource)->get();

        DB::transaction(fn () => $models->each(fn (Model $model) => $this->restoreModel($model)));
        $models->each(fn (Model $model) => $this->broadcastRestoredModel($model));

        $this->audit->record('system-trash', 'restore_all', 'success', $request, [
            'metadata' => ['resource' => $resource, 'count' => $models->count(), 'restore_mode' => 'complete'],
        ]);

        return $models->count();
    }

    private function log(Request $request, string $resource, Model $model, int $sourceId): void
    {
        $this->audit->record('system-trash', 'restore', 'success', $request, [
            'record_type' => $model::class,
            'record_id' => $model->getKey(),
            'record_label' => TrashRegistry::recordLabel($model),
            'observations' => 'Restauración completa del registro original.',
            'metadata' => [
                'resource' => $resource,
                'restore_mode' => 'complete',
                'source_record_id' => $sourceId,
            ],
        ]);
    }

    private function broadcastRestoredModel(Model $model): void
    {
        try {
            if ($model instanceof User) {
                $model->loadMissing(['role.permissions', 'permissions']);
                broadcast(new UserChanged($model, 'restored'))->toOthers();
            }

            if ($model instanceof Employee) {
                broadcast(new EmployeeChanged('restored', $model->getKey()))->toOthers();
                $linkedUser = $model->user()->withTrashed()->first();

                if ($linkedUser && !$linkedUser->trashed()) {
                    $linkedUser->loadMissing(['role.permissions', 'permissions']);
                    broadcast(new UserChanged($linkedUser, 'restored'))->toOthers();
                }
            }

            if ($model instanceof Branch) {
                broadcast(BranchChanged::fromBranch($model, 'restored'))->toOthers();
            }

            if ($model instanceof Product) {
                broadcast(new ProductChanged(
                    'restored',
                    $model->getKey(),
                    $model->branchProducts()->pluck('branch_id')->map(fn ($id) => (int) $id)->all(),
                ))->toOthers();
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Restores the original row and its soft-deleted dependent records. Every
     * recovery entry point delegates here, so a record is never recreated.
     */
    private function restoreModel(Model $model): void
    {
        $model->restore();

        if ($model instanceof Product) {
            $model->barcodes()->onlyTrashed()->get()->each->restore();
            $model->branchProducts()->onlyTrashed()->get()->each->restore();
        }

        if ($model instanceof Employee) {
            $linkedUser = $model->user()->withTrashed()->first();

            if ($linkedUser?->trashed()) {
                $linkedUser->restore();
            }
        }
    }

}
