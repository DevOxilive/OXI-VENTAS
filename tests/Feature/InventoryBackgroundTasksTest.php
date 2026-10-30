<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\InventoryTask;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryBackgroundContext;
use App\Services\InventoryOutbox;
use App\Services\InventoryTaskProcessor;
use App\Services\StockMovementService;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class InventoryBackgroundTasksTest extends TestCase
{
    use RefreshDatabase;

    private BranchProduct $inventory;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'queue.default' => 'sync', 'broadcasting.default' => 'null', 'scout.driver' => 'collection',
            'inventory-background.lease_seconds' => 300, 'inventory-background.max_attempts' => 8,
            'inventory-background.retry_delays' => [5, 15, 60],
        ]);
        $role = Role::create(['name' => 'Inventory background test']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.branches.stock-in']));
        $branch = Branch::create(['name' => 'Background inventory', 'slug' => 'background-inventory', 'active' => true]);
        $this->actor = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->actor->branches()->attach($branch);
        $this->actingAs($this->actor);
        $category = Category::create(['name' => 'Background inventory']);
        $product = Product::create([
            'name' => 'Producto en segundo plano', 'category_id' => $category->id,
            'cost' => 5, 'sale_price' => 10, 'cost_per_piece' => 5, 'sale_price_per_piece' => 10,
            'unit' => 'pza', 'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->inventory = BranchProduct::create([
            'branch_id' => $branch->id, 'product_id' => $product->id, 'stock' => 0,
            'min_stock' => 0, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
    }

    public function test_http_save_leaves_durable_pending_work_without_broadcasting_or_queueing_even_with_sync_queue(): void
    {
        $factory = Mockery::mock(BroadcastingFactory::class);
        $factory->shouldNotReceive('connection');
        $this->app->instance(BroadcastingFactory::class, $factory);
        Queue::fake();

        $this->post(route('inventory.stock-movements.store'), $this->incoming())
            ->assertRedirect()->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Movimiento de stock registrado correctamente.');

        $this->assertEquals(4, $this->inventory->fresh()->stock);
        $this->assertSame(1, $this->inventory->movements()->count());
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'stock.updated', 'status' => 'pending', 'attempts' => 0]);
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'activity', 'status' => 'pending', 'attempts' => 0]);
        $this->assertSame(0, InventoryTask::where('status', '<>', 'pending')->count());
        Queue::assertNothingPushed();
    }

    public function test_stock_movement_and_pending_notifications_roll_back_together(): void
    {
        try {
            DB::transaction(function (): void {
                $this->move();
                $this->assertGreaterThan(0, InventoryTask::count());
                throw new RuntimeException('Cancelar antes de confirmar');
            });
            $this->fail('El fallo anterior a la confirmación debe propagarse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cancelar antes de confirmar', $exception->getMessage());
        }

        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $this->assertSame(0, $this->inventory->movements()->count());
        $this->assertSame(0, $this->inventory->batches()->count());
        $this->assertDatabaseCount('inventory_tasks', 0);
    }

    public function test_worker_delivers_current_stock_without_repeating_the_original_movement(): void
    {
        $this->move();
        $this->onlyTaskType('stock.updated');
        DB::table('branch_products')->where('id', $this->inventory->id)->update(['stock' => 9]);
        $broadcaster = $this->broadcaster();
        $broadcaster->shouldReceive('broadcast')->once()->withArgs(function ($channels, $event, $payload): bool {
            return $event === 'stock.updated'
                && (float) $payload['stock'] === 9.0
                && $payload['branch_product_id'] === $this->inventory->id
                && (string) $channels[0] === 'inventory.branch.'.$this->inventory->branch_id;
        });

        $processor = app(InventoryTaskProcessor::class);
        $this->assertTrue($processor->processNext());
        $this->assertFalse($processor->processNext());
        $this->assertDatabaseHas('inventory_tasks', ['status' => 'completed', 'attempts' => 1]);
        $this->assertEquals(9, $this->inventory->fresh()->stock);
        $this->assertSame(1, $this->inventory->movements()->count());
        $this->assertSame(1, $this->inventory->batches()->count());
    }

    public function test_a_failed_transport_is_delayed_while_other_pending_notifications_continue(): void
    {
        $this->travelTo(now()->startOfSecond());
        app(InventoryOutbox::class)->stockChanged($this->inventory, 'actualizó', 'existencias', 'update');
        $this->broadcaster()->shouldReceive('broadcast')->twice()->andReturnUsing(function ($channels, $event): void {
            if ($event === 'stock.updated') {
                throw new RuntimeException('Error de transporte con datos que no deben guardarse');
            }
        });
        $processor = app(InventoryTaskProcessor::class);

        $this->assertTrue($processor->processNext());
        $failedAttempt = InventoryTask::where('type', 'stock.updated')->firstOrFail();
        $this->assertSame('pending', $failedAttempt->status);
        $this->assertSame(1, $failedAttempt->attempts);
        $this->assertTrue($failedAttempt->available_at->equalTo(now()->addSeconds(5)));
        $this->assertSame(RuntimeException::class, $failedAttempt->last_error);
        $this->assertTrue($processor->processNext());
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'activity', 'status' => 'completed']);
        $this->assertFalse($processor->processNext());
        $this->assertEquals(0, $this->inventory->fresh()->stock);
    }

    public function test_exhausted_work_is_visible_and_can_be_manually_retried_without_replaying_stock(): void
    {
        $this->travelTo(now()->startOfSecond());
        config(['inventory-background.max_attempts' => 2]);
        app(InventoryOutbox::class)->record('stock.updated', ['branch_product_id' => $this->inventory->id]);
        $this->broadcaster()->shouldReceive('broadcast')->twice()->andThrow(new RuntimeException('Sin transporte'));
        $processor = app(InventoryTaskProcessor::class);

        $processor->processNext();
        $this->travel(6)->seconds();
        $processor->processNext();
        $task = InventoryTask::firstOrFail();
        $this->assertSame('failed', $task->status);
        $this->assertSame(2, $task->attempts);
        $this->assertNotNull($task->failed_at);
        $this->assertTrue($processor->retryFailed($task->id));
        $this->assertFalse($processor->retryFailed($task->id));
        $this->assertSame(0, $task->fresh()->attempts);
        $this->assertNull($task->fresh()->last_error);

        $this->broadcaster()->shouldReceive('broadcast')->once();
        $processor = app(InventoryTaskProcessor::class);
        $this->assertTrue($processor->processNext());
        $this->assertSame('completed', $task->fresh()->status);
        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $this->assertSame(0, $this->inventory->movements()->count());
        $this->assertFalse($processor->retryFailed($task->id));
    }

    public function test_expired_lease_is_recovered_and_a_late_worker_cannot_acknowledge_the_new_owner(): void
    {
        $this->travelTo(now()->startOfSecond());
        app(InventoryOutbox::class)->record('stock.updated', ['branch_product_id' => $this->inventory->id]);
        $processor = app(InventoryTaskProcessor::class);
        $oldOwner = $processor->claim();
        $this->assertNotNull($oldOwner);
        $this->assertNull($processor->claim());
        $this->assertFalse($processor->retryFailed($oldOwner->id));

        $this->travel(301)->seconds();
        $newOwner = $processor->claim();
        $this->assertNotNull($newOwner);
        $this->assertSame($oldOwner->id, $newOwner->id);
        $this->assertNotSame($oldOwner->lock_token, $newOwner->lock_token);
        $this->assertSame(2, $newOwner->attempts);
        $this->assertFalse($processor->complete($oldOwner));
        $this->assertFalse($processor->fail($oldOwner, new RuntimeException('Worker anterior')));
        $this->assertTrue($processor->complete($newOwner));
        $this->assertSame('completed', $newOwner->fresh()->status);
    }

    public function test_activity_retains_the_original_actor_when_the_worker_has_no_authenticated_user(): void
    {
        $actorName = $this->actor->name;
        $actorEmail = $this->actor->email;
        $actorId = $this->actor->id;
        app(InventoryOutbox::class)->stockChanged($this->inventory, 'registró', 'existencias', 'in');
        $this->onlyTaskType('activity');
        Auth::logout();
        $this->assertNull(Auth::user());
        $this->broadcaster()->shouldReceive('broadcast')->once()->withArgs(function ($channels, $event, $payload) use ($actorName, $actorEmail, $actorId): bool {
            return $event === 'realtime.activity'
                && $payload['actorName'] === $actorName
                && $payload['actorEmail'] === $actorEmail
                && str_contains($payload['message'], $actorEmail)
                && in_array('private-users.'.$actorId, array_map('strval', $channels), true);
        });

        $this->assertTrue(app(InventoryTaskProcessor::class)->processNext());
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'activity', 'status' => 'completed']);
    }

    public function test_search_indexing_is_atomic_with_the_catalog_and_never_calls_the_engine_during_http_save(): void
    {
        $engine = $this->searchEngine();
        $engine->shouldNotReceive('update');
        $engine->shouldNotReceive('delete');
        $product = $this->inventory->product;
        $context = app(InventoryBackgroundContext::class);
        try {
            $context->run(fn () => DB::transaction(function () use ($product): void {
                $product->update(['name' => 'Cambio que se debe revertir']);
                $this->assertDatabaseHas('inventory_tasks', ['type' => 'product.search', 'status' => 'pending']);
                throw new RuntimeException('Revertir catálogo e índice');
            }));
        } catch (RuntimeException $exception) {
            $this->assertSame('Revertir catálogo e índice', $exception->getMessage());
        }

        $this->assertSame('Producto en segundo plano', $product->fresh()->name);
        $this->assertDatabaseCount('inventory_tasks', 0);
        $this->assertFalse($context->active());
    }

    public function test_search_worker_indexes_the_current_product_rather_than_a_stale_snapshot(): void
    {
        $product = $this->inventory->product;
        app(InventoryOutbox::class)->searchProduct($product->id);
        DB::table('products')->where('id', $product->id)->update(['name' => 'Nombre más reciente']);
        $this->searchEngine()->shouldReceive('update')->once()->withArgs(fn ($models) => $models->count() === 1 && $models->first()->id === $product->id
            && $models->first()->name === 'Nombre más reciente');

        $this->assertTrue(app(InventoryTaskProcessor::class)->processNext());
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'product.search', 'status' => 'completed']);
    }

    public function test_search_worker_removes_a_product_deleted_after_the_task_was_saved(): void
    {
        $product = $this->inventory->product;
        app(InventoryOutbox::class)->searchProduct($product->id);
        DB::table('products')->where('id', $product->id)->update(['deleted_at' => now()]);
        $engine = $this->searchEngine();
        $engine->shouldNotReceive('update');
        $engine->shouldReceive('delete')->once()->withArgs(fn ($models) => $models->first()->id === $product->id);

        $this->assertTrue(app(InventoryTaskProcessor::class)->processNext());
        $this->assertDatabaseHas('inventory_tasks', ['type' => 'product.search', 'status' => 'completed']);
    }

    public function test_scout_outside_inventory_context_keeps_its_existing_immediate_behavior(): void
    {
        $product = $this->inventory->product;
        $this->searchEngine()->shouldReceive('update')->once()->withArgs(fn ($models) => $models->first()->id === $product->id);

        $product->update(['name' => 'Cambio ajeno al contexto de inventario']);

        $this->assertDatabaseCount('inventory_tasks', 0);
        $this->assertFalse(app(InventoryBackgroundContext::class)->active());
    }

    public function test_worker_command_respects_its_limit_and_leaves_remaining_work_pending(): void
    {
        app(InventoryOutbox::class)->record('stock.updated', ['branch_product_id' => $this->inventory->id]);
        app(InventoryOutbox::class)->record('stock.updated', ['branch_product_id' => $this->inventory->id]);
        $this->broadcaster()->shouldReceive('broadcast')->once();

        $this->artisan('inventory:work', ['--limit' => 1, '--max-time' => 1])->assertSuccessful();

        $this->assertSame(1, InventoryTask::where('status', 'completed')->count());
        $this->assertSame(1, InventoryTask::where('status', 'pending')->count());
    }

    public function test_failure_to_persist_an_audit_notice_rolls_back_the_catalog_change(): void
    {
        $product = $this->inventory->product;
        $originalName = $product->name;
        $eventName = 'eloquent.creating: '.InventoryTask::class;
        Event::listen($eventName, function (InventoryTask $task): void {
            if ($task->type === 'system.audit.changed') {
                throw new RuntimeException('Simular fallo al guardar el pendiente');
            }
        });

        try {
            app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(
                fn () => $product->update(['name' => 'Cambio que no debe confirmarse']),
            ));
            $this->fail('El cambio y el pendiente deben confirmarse juntos.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simular fallo al guardar el pendiente', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame($originalName, $product->fresh()->name);
        $this->assertDatabaseCount('inventory_tasks', 0);
        $this->assertFalse(app(InventoryBackgroundContext::class)->active());
    }

    public function test_scheduled_cleanup_keeps_retention_and_preserves_a_restored_product(): void
    {
        $this->travelTo(now()->startOfSecond());
        Queue::fake();
        $product = $this->inventory->product;
        app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(fn () => $product->delete()));
        $this->onlyTaskType('trash.purge');
        $scheduled = InventoryTask::firstOrFail();
        $this->assertTrue($scheduled->available_at->equalTo(now()->addDays(180)));
        $this->assertNull(app(InventoryTaskProcessor::class)->claim());
        Queue::assertNothingPushed();

        app(InventoryBackgroundContext::class)->run(fn () => DB::transaction(fn () => $product->restore()));
        $this->onlyTaskType('trash.purge');
        $this->travelTo($scheduled->available_at->copy()->addSecond());
        $this->assertTrue(app(InventoryTaskProcessor::class)->processNext());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
        $this->assertSame('completed', $scheduled->fresh()->status);
    }

    private function incoming(): array
    {
        return [
            'branch_product_id' => $this->inventory->id, 'type' => 'IN', 'reason' => 'PURCHASE', 'quantity' => 4,
            'batches' => [[
                'lot_number' => 'BACKGROUND-001', 'quantity' => 4,
                'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
            ]],
        ];
    }

    private function move(): void
    {
        app(StockMovementService::class)->move(
            $this->inventory, 'IN', 'PURCHASE', 4, userId: $this->actor->id,
            batches: $this->incoming()['batches'],
        );
    }

    private function onlyTaskType(string $type): void
    {
        InventoryTask::where('type', '<>', $type)->delete();
    }

    private function broadcaster(): MockInterface
    {
        $broadcaster = Mockery::mock(Broadcaster::class);
        $factory = Mockery::mock(BroadcastingFactory::class);
        $factory->shouldReceive('connection')->andReturn($broadcaster);
        $this->app->instance(BroadcastingFactory::class, $factory);

        return $broadcaster;
    }

    private function searchEngine(): MockInterface
    {
        $engine = Mockery::mock(Engine::class)->shouldIgnoreMissing();
        $manager = Mockery::mock(EngineManager::class);
        $manager->shouldReceive('engine')->andReturn($engine);
        $this->app->instance(EngineManager::class, $manager);

        return $engine;
    }
}
