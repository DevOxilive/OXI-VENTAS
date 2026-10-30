<?php

namespace Tests\Feature;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
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
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class InventoryBatchConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private BranchProduct $inventory;

    private ProductBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Event::fake([InventoryStockUpdated::class, RealtimeActivityLogged::class]);

        $role = Role::create(['name' => 'Batch concurrency test']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.branches.batches.update']));
        $branch = Branch::create(['name' => 'Batch test', 'slug' => 'batch-test', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Batch', 'last_name' => 'Test', 'email' => 'batch@example.test']);
        $user = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'is_active' => true]);
        $user->branches()->attach($branch);
        $this->actingAs($user);

        $category = Category::create(['name' => 'Batch test']);
        $product = Product::create([
            'name' => 'Producto de lote', 'category_id' => $category->id, 'cost' => 5,
            'sale_price' => 10, 'cost_per_piece' => 5, 'sale_price_per_piece' => 10,
            'unit' => 'pza', 'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->inventory = BranchProduct::create([
            'branch_id' => $branch->id, 'product_id' => $product->id, 'stock' => 10,
            'min_stock' => 0, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->batch = ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => 'CONCURRENT-001',
            'quantity' => 10, 'initial_quantity' => 10, 'supplier' => 'Original',
            'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
            'status' => ProductBatch::STATUS_ACTIVE,
        ]);
    }

    public function test_two_captures_from_the_same_initial_stock_apply_both_deltas(): void
    {
        // Ambas capturas pueden provenir del saldo inicial 10: el servidor no lo reutiliza.
        $this->put($this->batchUrl(), $this->delta(5))->assertSessionHasNoErrors();
        $this->put($this->batchUrl(), $this->delta(3))->assertSessionHasNoErrors();

        $this->assertStock(18);
        $this->assertSame([5.0, 3.0], StockMovement::where('branch_product_id', $this->inventory->id)
            ->orderBy('id')->get()->map(fn ($movement) => (float) $movement->quantity)->all());
    }

    public function test_metadata_edit_preserves_a_quantity_changed_after_the_form_was_opened(): void
    {
        $this->put($this->batchUrl(), $this->delta(5))->assertSessionHasNoErrors();
        $this->put($this->batchUrl(), [
            'supplier' => 'Nuevo proveedor', 'expected_values' => ['supplier' => 'Original'],
        ])->assertSessionHasNoErrors();

        $this->assertStock(15);
        $this->assertSame('Nuevo proveedor', $this->batch->fresh()->supplier);
        $this->assertEquals(0, StockMovement::latest('id')->firstOrFail()->quantity);
    }

    public function test_delta_preserves_metadata_updated_by_another_capture(): void
    {
        $this->put($this->batchUrl(), [
            'supplier' => 'Nuevo proveedor', 'expected_values' => ['supplier' => 'Original'],
        ])->assertSessionHasNoErrors();
        $this->put($this->batchUrl(), $this->delta(-3))->assertSessionHasNoErrors();

        $this->assertStock(7);
        $this->assertSame('Nuevo proveedor', $this->batch->fresh()->supplier);
        $this->assertSame(ProductBatch::STATUS_ACTIVE, $this->batch->fresh()->status);
    }

    public function test_conflicting_metadata_rejects_the_whole_operation_including_its_delta(): void
    {
        $this->put($this->batchUrl(), [
            'supplier' => 'Proveedor A', 'expected_values' => ['supplier' => 'Original'],
        ])->assertSessionHasNoErrors();
        $this->putJson($this->batchUrl(), [
            'supplier' => 'Proveedor B', 'expected_values' => ['supplier' => 'Original', 'status' => 'ACTIVE'], 'quantity_delta' => 5,
        ])->assertUnprocessable()->assertJsonValidationErrors('supplier');

        $this->assertStock(10);
        $this->assertSame('Proveedor A', $this->batch->fresh()->supplier);
        $this->assertSame(1, $this->inventory->movements()->count());
    }

    public function test_metadata_cannot_be_overwritten_without_its_expected_value(): void
    {
        $this->putJson($this->batchUrl(), ['supplier' => 'Sin comprobar'])
            ->assertUnprocessable()->assertJsonValidationErrors('supplier');

        $this->assertSame('Original', $this->batch->fresh()->supplier);
        $this->assertSame(0, $this->inventory->movements()->count());
    }

    public function test_two_captures_editing_different_fields_preserve_both_changes(): void
    {
        $this->put($this->batchUrl(), [
            'supplier' => 'Proveedor A', 'expected_values' => ['supplier' => 'Original'],
        ])->assertSessionHasNoErrors();
        $this->put($this->batchUrl(), [
            'lot_number' => 'CONCURRENT-002', 'expected_values' => ['lot_number' => 'CONCURRENT-001'],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Proveedor A', $this->batch->fresh()->supplier);
        $this->assertSame('CONCURRENT-002', $this->batch->fresh()->lot_number);
        $this->assertStock(10);
    }

    public function test_absolute_quantity_requires_the_expected_quantity_and_rejects_a_stale_balance(): void
    {
        $this->putJson($this->batchUrl(), ['quantity' => 15])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_quantity');
        $this->put($this->batchUrl(), $this->delta(5))->assertSessionHasNoErrors();
        $this->putJson($this->batchUrl(), ['quantity' => 13, 'expected_quantity' => 10, 'expected_values' => ['status' => 'ACTIVE']])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertStock(15);
        $this->assertSame(1, $this->inventory->movements()->count());
    }

    public function test_inactivation_and_reactivation_audit_the_available_stock_difference(): void
    {
        $this->put($this->batchUrl(), [
            'status' => ProductBatch::STATUS_INACTIVE, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $inactiveMovement = StockMovement::latest('id')->firstOrFail();
        $this->assertEquals(-10, $inactiveMovement->quantity);
        $this->assertEquals(10, $inactiveMovement->previous_stock);
        $this->assertEquals(0, $inactiveMovement->new_stock);
        $this->assertStringContainsString('"ACTIVE" → "INACTIVE"', $inactiveMovement->notes);
        $this->assertSame($this->batch->id, $inactiveMovement->batches()->firstOrFail()->product_batch_id);

        $this->put($this->batchUrl(), [
            'status' => ProductBatch::STATUS_ACTIVE, 'expected_values' => ['status' => ProductBatch::STATUS_INACTIVE],
        ])->assertSessionHasNoErrors();
        $this->assertStock(10);
        $this->assertEquals(10, StockMovement::latest('id')->firstOrFail()->quantity);
    }

    public function test_negative_balance_is_preserved_and_its_inactivation_has_a_positive_stock_delta(): void
    {
        $this->put($this->batchUrl(), $this->delta(-13))->assertSessionHasNoErrors();
        $this->assertStock(-3);

        $this->put($this->batchUrl(), [
            'status' => ProductBatch::STATUS_INACTIVE, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE],
        ])->assertSessionHasNoErrors();

        $this->assertEquals(-3, $this->batch->fresh()->quantity);
        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $this->assertEquals(3, StockMovement::latest('id')->firstOrFail()->quantity);
    }

    public function test_quantity_change_requires_the_status_that_was_visible_when_the_capture_started(): void
    {
        $this->putJson($this->batchUrl(), ['quantity_delta' => 2])
            ->assertUnprocessable()->assertJsonValidationErrors('expected_values.status');

        $this->assertStock(10);
        $this->assertSame(0, $this->inventory->movements()->count());
    }

    public function test_inactivation_after_opening_the_form_rejects_its_old_delta_without_reactivating_the_batch(): void
    {
        $capture = $this->delta(20);
        $this->put($this->batchUrl(), [
            'status' => ProductBatch::STATUS_INACTIVE, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE],
        ])->assertSessionHasNoErrors();

        $this->putJson($this->batchUrl(), $capture)->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson($this->batchUrl(), [
            'quantity' => 30, 'expected_quantity' => 10, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE],
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertSame(ProductBatch::STATUS_INACTIVE, $this->batch->fresh()->status);
        $this->assertEquals(10, $this->batch->fresh()->quantity);
        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $this->assertSame(1, $this->inventory->movements()->count());
    }

    public function test_piece_quantity_does_not_accept_a_fractional_delta(): void
    {
        $this->putJson($this->batchUrl(), $this->delta(0.5))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');

        $this->assertStock(10);
        $this->assertSame(0, $this->inventory->movements()->count());
    }

    public function test_absolute_quantity_and_delta_cannot_be_mixed(): void
    {
        $this->putJson($this->batchUrl(), ['quantity' => 15, 'expected_quantity' => 10, ...$this->delta(5)])
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity', 'quantity_delta']);

        $this->assertStock(10);
    }

    public function test_a_broadcast_failure_does_not_report_a_confirmed_update_as_failed(): void
    {
        Event::shouldReceive('dispatch')->andReturnUsing(function ($event) {
            if ($event instanceof InventoryStockUpdated) {
                throw new RuntimeException('Canal temporalmente no disponible');
            }

            return null;
        });

        $this->put($this->batchUrl(), $this->delta(2))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertStock(12);
        $this->assertSame(1, $this->inventory->movements()->count());
    }

    private function batchUrl(): string
    {
        return route('inventory.product-batches.update', $this->batch);
    }

    private function delta(float $quantity): array
    {
        return ['quantity_delta' => $quantity, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE]];
    }

    private function assertStock(float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) $this->batch->fresh()->quantity, 0.000001);
        $this->assertEqualsWithDelta($expected, (float) $this->inventory->fresh()->stock, 0.000001);
    }
}
