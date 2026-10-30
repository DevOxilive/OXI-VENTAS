<?php

namespace App\Services;

use App\Events\InventoryActivityLogged;
use App\Events\InventoryStockUpdated;
use App\Events\ProductChanged;
use App\Events\SystemAuditChanged;
use App\Events\SystemTrashChanged;
use App\Jobs\PurgeTrashRecord;
use App\Models\BranchProduct;
use App\Models\InventoryTask;
use App\Models\Product;
use App\Models\User;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Laravel\Scout\Engines\MeilisearchEngine;
use Meilisearch\Client as SearchClient;
use Throwable;

class InventoryTaskProcessor
{
    private ?Broadcaster $broadcaster = null;

    /** Reserve one task briefly; external requests never run inside this lock. */
    public function claim(): ?InventoryTask
    {
        return (new InventoryTask)->getConnection()->transaction(function (): ?InventoryTask {
            while (true) {
                $task = InventoryTask::query()
                    ->where(function ($query): void {
                        $query->where(function ($pending): void {
                            $pending->where('status', InventoryTask::STATUS_PENDING)
                                ->where('available_at', '<=', now());
                        })->orWhere(function ($expired): void {
                            $expired->where('status', InventoryTask::STATUS_PROCESSING)
                                ->where('locked_at', '<=', now()->subSeconds($this->leaseSeconds()));
                        });
                    })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if (! $task) {
                    return null;
                }

                if ($task->attempts >= $this->maxAttempts()) {
                    $task->forceFill([
                        'status' => InventoryTask::STATUS_FAILED,
                        'failed_at' => now(),
                        'locked_at' => null,
                        'lock_token' => null,
                        'last_error' => $task->last_error ?? 'WorkerLeaseExpired',
                    ])->save();

                    continue;
                }

                $task->forceFill([
                    'status' => InventoryTask::STATUS_PROCESSING,
                    'attempts' => $task->attempts + 1,
                    'locked_at' => now(),
                    'lock_token' => (string) Str::uuid(),
                ])->save();

                return $task;
            }
        }, 3);
    }

    public function processNext(): bool
    {
        $task = $this->claim();

        if (! $task) {
            return false;
        }

        try {
            $this->deliver($task);
            $this->complete($task);
        } catch (Throwable $exception) {
            $this->fail($task, $exception);
            Log::warning('Inventory background task delivery failed.', [
                'task_id' => $task->id,
                'type' => $task->type,
                'attempt' => $task->attempts,
                'error_class' => $exception::class,
            ]);
        }

        return true;
    }

    /** Deliver notifications/indexing only. Never replay a business movement. */
    public function deliver(InventoryTask $task): void
    {
        $payload = $task->payload;

        switch ($task->type) {
            case 'stock.updated':
                $branchProduct = BranchProduct::query()->find((int) $payload['branch_product_id']);

                // A later product removal makes this old stock notice obsolete.
                if ($branchProduct) {
                    $this->broadcast(new InventoryStockUpdated($branchProduct));
                }
                break;

            case 'product.changed':
                $this->broadcast(new ProductChanged(
                    (string) $payload['action'],
                    (int) $payload['product_id'],
                    $payload['branch_ids'] ?? [],
                ), $payload['socket_id'] ?? null);
                break;

            case 'activity':
                $actor = $payload['actor'] ?? [];
                $entityName = $payload['entity_name'] ?? null;
                $suffix = filled($entityName) ? ': '.$entityName : '';
                $actorEmail = $actor['email'] ?? 'sistema';
                $this->broadcast(new InventoryActivityLogged(
                    $actor['name'] ?? 'Sistema',
                    $actorEmail,
                    "El usuario {$actorEmail} {$payload['action_label']} {$payload['entity_label']}{$suffix}",
                    $payload['module'] ?? 'Inventario',
                    (string) $payload['action'],
                    $entityName,
                    $this->activityRecipients(isset($actor['id']) ? (int) $actor['id'] : null),
                ));
                break;

            case 'product.search':
                $this->searchProduct((int) $payload['product_id']);
                break;

            case 'system.audit.changed':
                $this->broadcast(new SystemAuditChanged(
                    (string) $payload['module'],
                    (string) $payload['action'],
                    isset($payload['actor_id']) ? (int) $payload['actor_id'] : null,
                    $payload['route_name'] ?? null,
                    $payload['result'] ?? 'success',
                ), $payload['socket_id'] ?? null);
                break;

            case 'system.trash.changed':
                $this->broadcast(new SystemTrashChanged(
                    (string) $payload['resource'],
                    (string) $payload['action'],
                    $payload['record_ids'] ?? [],
                ));
                break;

            case 'trash.purge':
                app(InventoryBackgroundContext::class)->run(fn () => (new InventoryTask)->getConnection()->transaction(
                    fn () => (new PurgeTrashRecord(
                        (string) $payload['resource'],
                        (int) $payload['record_id'],
                    ))->handle(app(SystemTrashPurgeService::class)),
                ));
                break;

            default:
                throw new InvalidArgumentException('Unsupported inventory task type.');
        }
    }

    /** A late worker cannot acknowledge a task already reserved by another worker. */
    public function complete(InventoryTask $task): bool
    {
        return $this->ownedTask($task)->update([
            'status' => InventoryTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'failed_at' => null,
            'locked_at' => null,
            'lock_token' => null,
            'last_error' => null,
            'updated_at' => now(),
        ]) === 1;
    }

    public function fail(InventoryTask $task, Throwable $exception): bool
    {
        $exhausted = $task->attempts >= $this->maxAttempts();
        $delays = (array) config('inventory-background.retry_delays', [5, 15, 60, 180, 600, 1800, 3600]);
        $delay = max(1, (int) ($delays[min(max(0, $task->attempts - 1), count($delays) - 1)] ?? 60));

        return $this->ownedTask($task)->update([
            'status' => $exhausted ? InventoryTask::STATUS_FAILED : InventoryTask::STATUS_PENDING,
            'available_at' => now()->addSeconds($delay),
            'failed_at' => $exhausted ? now() : null,
            'locked_at' => null,
            'lock_token' => null,
            // Exception messages may contain credentials or product/user information.
            'last_error' => substr($exception::class, 0, 255),
            'updated_at' => now(),
        ]) === 1;
    }

    /** Operators may retry exhausted work, never steal a pending/active lease. */
    public function retryFailed(int $id): bool
    {
        return InventoryTask::query()->whereKey($id)
            ->where('status', InventoryTask::STATUS_FAILED)
            ->update([
                'status' => InventoryTask::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => now(),
                'failed_at' => null,
                'completed_at' => null,
                'locked_at' => null,
                'lock_token' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    private function ownedTask(InventoryTask $task): Builder
    {
        return InventoryTask::query()->whereKey($task->id)
            ->where('status', InventoryTask::STATUS_PROCESSING)
            ->where('lock_token', $task->lock_token);
    }

    private function searchProduct(int $id): void
    {
        $product = Product::withTrashed()->find($id);
        $reference = $product ?? new Product;
        $engine = $reference->searchableUsing();

        if ($engine instanceof MeilisearchEngine) {
            // Bound this worker's external calls without changing interactive search.
            $engine = new MeilisearchEngine(new SearchClient(
                (string) config('scout.meilisearch.host'),
                config('scout.meilisearch.key'),
                new HttpClient(['connect_timeout' => 5, 'timeout' => 30]),
            ), (bool) config('scout.soft_delete', false), (array) config('scout.meilisearch', []));
        }

        if (! $product) {
            $missing = new Product;
            $missing->setAttribute($missing->getKeyName(), $id);
            $engine->delete($missing->newCollection([$missing]));

            return;
        }

        if ($product->trashed() || ! $product->shouldBeSearchable()) {
            $engine->delete($product->newCollection([$product]));

            return;
        }

        $engine->update($product->makeSearchableUsing($product->newCollection([$product])));
    }

    private function activityRecipients(?int $actorId): array
    {
        return User::query()->where('is_active', true)
            ->where(function ($query) use ($actorId): void {
                $query->whereHas('role', fn ($roles) => $roles
                    ->whereIn('name', ['Administrador', 'Super Administrador']));

                if ($actorId) {
                    $query->orWhere('users.id', $actorId);
                }
            })
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    private function broadcast(object $event, ?string $socket = null): void
    {
        $payload = method_exists($event, 'broadcastWith')
            ? $event->broadcastWith()
            : get_object_vars($event);
        $payload['socket'] = $socket;

        // Direct delivery here avoids both sync HTTP dispatch and nested queue jobs.
        $this->broadcaster()->broadcast(
            Arr::wrap($event->broadcastOn()), $event->broadcastAs(), $payload,
        );
    }

    private function broadcaster(): Broadcaster
    {
        if ($this->broadcaster) {
            return $this->broadcaster;
        }

        $manager = app(BroadcastingFactory::class);
        $configuration = (array) config('broadcasting.connections.'.config('broadcasting.default'), []);

        if ($manager instanceof BroadcastManager && in_array($configuration['driver'] ?? null, ['reverb', 'pusher'], true)) {
            // This private client leaves the application's shared broadcaster intact.
            $configuration['client_options'] = array_merge($configuration['client_options'] ?? [], [
                'connect_timeout' => 5,
                'timeout' => 30,
            ]);

            return $this->broadcaster = new PusherBroadcaster($manager->pusher($configuration));
        }

        return $this->broadcaster = $manager->connection();
    }

    private function leaseSeconds(): int
    {
        return max(30, (int) config('inventory-background.lease_seconds', 300));
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('inventory-background.max_attempts', 8));
    }
}
