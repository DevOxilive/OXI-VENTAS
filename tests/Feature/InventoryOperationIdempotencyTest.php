<?php

namespace Tests\Feature;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Http\Middleware\PreventDuplicateInventoryOperation;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class InventoryOperationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private BranchProduct $inventory;

    private ProductBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Event::fake([InventoryStockUpdated::class, RealtimeActivityLogged::class]);
        $role = Role::create(['name' => 'Inventory operation test']);
        foreach (['inventory.branches.batches.update', 'inventory.branches.stock-in', 'inventory.branches.stock-out'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }
        $branch = Branch::create(['name' => 'Operation test', 'slug' => 'operation-test', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Operation', 'last_name' => 'Test', 'email' => 'operation@example.test']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'is_active' => true]);
        $this->user->branches()->attach($branch);
        $this->actingAs($this->user);
        $category = Category::create(['name' => 'Operation test']);
        $product = Product::create([
            'name' => 'Producto de operación', 'category_id' => $category->id,
            'cost' => 5, 'sale_price' => 10, 'cost_per_piece' => 5, 'sale_price_per_piece' => 10,
            'unit' => 'pza', 'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->inventory = BranchProduct::create([
            'branch_id' => $branch->id, 'product_id' => $product->id, 'stock' => 10,
            'min_stock' => 0, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->batch = ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => 'OPERATION-001',
            'quantity' => 10, 'initial_quantity' => 10, 'status' => ProductBatch::STATUS_ACTIVE,
            'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
        ]);
    }

    public function test_batch_retry_returns_the_saved_result_without_repeating_the_delta(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), $this->delta(5))
            ->assertRedirect()->assertSessionHasNoErrors();

        Cache::flush();
        $this->travel(2)->days();
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), $this->delta(5))
            ->assertRedirect()->assertHeader('Idempotency-Replayed', 'true')
            ->assertSessionHas('success', 'Lote actualizado correctamente.');

        $this->assertEquals(15, $this->inventory->fresh()->stock);
        $this->assertSame(1, $this->inventory->movements()->count());
        $this->assertDatabaseCount('inventory_operations', 1);
    }

    public function test_same_key_with_changed_content_is_rejected_and_new_key_is_an_independent_intention(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), $this->delta(5))
            ->assertSessionHasNoErrors();
        $this->withHeader('Idempotency-Key', $key)->putJson($this->batchUrl(), $this->delta(3))
            ->assertConflict();
        $this->withHeader('Idempotency-Key', (string) Str::uuid())->put($this->batchUrl(), $this->delta(5))
            ->assertSessionHasNoErrors();

        $this->assertEquals(20, $this->inventory->fresh()->stock);
        $this->assertSame(2, $this->inventory->movements()->count());
        $this->assertDatabaseCount('inventory_operations', 2);
    }

    public function test_validation_failure_does_not_reserve_the_key_or_store_a_success(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->putJson($this->batchUrl(), $this->delta(0.5))
            ->assertUnprocessable();
        $this->assertDatabaseCount('inventory_operations', 0);
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), $this->delta(3))
            ->assertSessionHasNoErrors();

        $this->assertEquals(13, $this->inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_operations', 1);
    }

    public function test_movement_retry_restores_its_original_movement_id_and_does_not_create_another_batch(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'branch_product_id' => $this->inventory->id, 'type' => 'IN', 'reason' => 'PURCHASE', 'quantity' => 4,
            'batches' => [[
                'lot_number' => 'OPERATION-002', 'quantity' => 4,
                'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
            ]],
        ];
        $url = route('inventory.stock-movements.store');
        $this->withHeader('Idempotency-Key', $key)->post($url, $payload)->assertSessionHasNoErrors();
        $movementId = StockMovement::latest('id')->firstOrFail()->id;
        $this->withHeader('Idempotency-Key', $key)->post($url, $payload)
            ->assertHeader('Idempotency-Replayed', 'true')->assertSessionHas('movement_id', $movementId);

        $this->assertEquals(14, $this->inventory->fresh()->stock);
        $this->assertSame(1, $this->inventory->movements()->count());
        $this->assertSame(2, $this->inventory->batches()->count());
    }

    public function test_caught_movement_validation_error_rolls_back_the_claim_and_allows_correction(): void
    {
        $key = (string) Str::uuid();
        $payload = [
            'branch_product_id' => $this->inventory->id, 'type' => 'IN', 'reason' => 'PURCHASE', 'quantity' => 4,
        ];
        $this->withHeader('Idempotency-Key', $key)->post(route('inventory.stock-movements.store'), $payload)
            ->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('inventory_operations', 0);
        $this->assertEquals(10, $this->inventory->fresh()->stock);
        $this->assertSame(0, $this->inventory->movements()->count());
    }

    public function test_payload_field_order_does_not_change_the_identity_of_a_retry(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), [...$this->delta(1), 'notes' => 'Orden'])
            ->assertSessionHasNoErrors();
        $this->withHeader('Idempotency-Key', $key)->put($this->batchUrl(), ['notes' => 'Orden', ...$this->delta(1)])
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertEquals(11, $this->inventory->fresh()->stock);
    }

    public function test_a_real_failure_before_commit_rolls_back_inventory_and_operation_together(): void
    {
        $request = $this->middlewareRequest();
        try {
            app(PreventDuplicateInventoryOperation::class)->handle($request, function () {
                $this->inventory->increment('stock', 7);
                throw new RuntimeException('Fallo antes de confirmar');
            });
            $this->fail('La excepción original debe propagarse.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo antes de confirmar', $exception->getMessage());
        }

        $this->assertEquals(10, $this->inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_operations', 0);
    }

    public function test_a_nested_after_commit_failure_returns_the_confirmed_response_without_repeating_writes(): void
    {
        $request = $this->middlewareRequest();
        $next = function () {
            DB::transaction(function () {
                $this->inventory->increment('stock', 7);
                DB::afterCommit(fn () => throw new RuntimeException('Aviso después de confirmar'));
            });

            return response()->json(['saved' => true], 201);
        };
        $middleware = app(PreventDuplicateInventoryOperation::class);
        $response = $middleware->handle($request, $next);
        $replay = $middleware->handle($request, fn () => throw new RuntimeException('No debe volver a ejecutarse'));

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('true', $replay->headers->get('Idempotency-Replayed'));
        $this->assertEquals(17, $this->inventory->fresh()->stock);
        $this->assertDatabaseCount('inventory_operations', 1);
    }

    private function batchUrl(): string
    {
        return route('inventory.product-batches.update', $this->batch);
    }

    private function middlewareRequest(): Request
    {
        $request = Request::create($this->batchUrl(), 'PUT', $this->delta(7));
        $request->headers->set('Idempotency-Key', (string) Str::uuid());
        $request->setUserResolver(fn () => $this->user);
        $route = (new Route('PUT', 'inventory/product-batches/{productBatch}', fn () => null))
            ->name('inventory.product-batches.update');
        $request->setRouteResolver(fn () => $route);

        return $request;
    }

    private function delta(float $quantity): array
    {
        return ['quantity_delta' => $quantity, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE]];
    }
}
