<?php

namespace Tests\Feature;

use App\Events\InventoryStockUpdated;
use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use App\Search\ProductIdentifierSearch;
use App\Search\ProductSearchOptions;
use App\Services\InventoryReportService;
use App\Services\PhysicalCountSnapshotService;
use App\Services\Reports\SalesReplenishmentReportService;
use App\Services\SaleCancellationService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NegativeStockTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private BranchProduct $inventory;

    private PaymentMethod $payment;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);
        Event::fake();
        $role = Role::create(['name' => 'Stock test']);
        foreach (['sales.create', 'inventory.branches.view', 'inventory.branches.stock-out', 'inventory.branches.stock-adjust', 'inventory.branches.batches.update', 'inventory.products.delete', 'audits.physical-counts.apply'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }
        $this->branch = Branch::create(['name' => 'Stock test', 'slug' => 'stock-test', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Stock', 'last_name' => 'Test', 'email' => 'stock@example.test']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'is_active' => true]);
        $this->user->branches()->attach($this->branch);
        $this->actingAs($this->user);
        $category = Category::create(['name' => 'Stock test']);
        $product = Product::create([
            'name' => 'Producto de prueba', 'category_id' => $category->id, 'cost' => 5,
            'sale_price' => 10, 'cost_per_piece' => 5, 'sale_price_per_piece' => 10,
            'unit' => 'pza', 'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->inventory = BranchProduct::create([
            'branch_id' => $this->branch->id, 'product_id' => $product->id, 'stock' => 0,
            'min_stock' => 2, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->payment = PaymentMethod::create(['name' => 'Efectivo', 'active' => true]);
    }

    public static function startingStocks(): array
    {
        return ['zero' => [0, false], 'negative' => [-4, false], 'tracked zero' => [0, true], 'tracked negative' => [-4, true], 'insufficient' => [2, true]];
    }

    #[DataProvider('startingStocks')]
    public function test_sale_can_exceed_stock_and_preserves_batch_and_movement_totals(float $stock, bool $withBatch): void
    {
        $this->inventory->update(['stock' => $stock]);
        if ($withBatch) {
            $this->batch($stock);
        }
        $sale = $this->sell(3);
        $this->assertStock($stock - 3);
        $movement = $sale->stockMovements()->firstOrFail();
        $this->assertEquals($stock, $movement->previous_stock);
        $this->assertEquals($stock - 3, $movement->new_stock);
        $this->assertEquals(3, $movement->batches()->sum('quantity'));
        $this->assertEquals(30, $sale->total);
        Event::assertDispatched(InventoryStockUpdated::class);
    }

    public function test_repeated_sales_restock_and_partial_returns_keep_the_deficit(): void
    {
        $this->batch(2);
        $this->inventory->update(['stock' => 2]);
        $sale = $this->sell(5);
        $this->sell(2);
        $this->assertStock(-5);
        $this->incoming(4);
        $this->assertStock(-1);
        $service = app(SaleCancellationService::class);
        $detail = $sale->details()->firstOrFail();
        $service->cancel($sale, $this->user, 'Devolución de prueba', [['sale_detail_id' => $detail->id, 'quantity' => 2]]);
        $this->assertStock(1);
        $service->cancel($sale->fresh(), $this->user, 'Resto de la devolución', [['sale_detail_id' => $detail->id, 'quantity' => 3]]);
        $this->assertStock(4);
        $this->assertSame('cancelled', $sale->fresh()->status);
    }

    public function test_boxes_and_kilograms_deduct_the_correct_base_quantity(): void
    {
        $this->inventory->product->update(['has_box_presentation' => true, 'pieces_per_box' => 12, 'sale_price_per_box' => 100]);
        $this->sell(2, 'box');
        $this->assertStock(-24);
        $this->inventory->product->update(['inventory_unit' => 'kg', 'unit' => 'kg']);
        $this->sell(0.125);
        $this->assertStock(-24.125);
    }

    public function test_independent_box_and_piece_prices_charge_and_deduct_the_correct_quantities(): void
    {
        $this->inventory->product->update([
            'has_box_presentation' => true, 'pieces_per_box' => 16,
            'cost_per_piece' => 0, 'sale_price_per_piece' => 18,
            'cost_per_box' => 180, 'sale_price_per_box' => 234,
        ]);
        $pieceSale = $this->sell(1);
        $this->assertEquals(18, $pieceSale->total);
        $boxSale = $this->sell(1, 'box');
        $this->assertEquals(234, $boxSale->total);
        $this->assertStock(-17);
    }

    public function test_entry_and_distribution_preserve_previous_untracked_negative_stock(): void
    {
        $this->inventory->update(['stock' => -5, 'tracks_batches' => false]);
        $this->incoming(2);
        $this->assertStock(-3);
        app(StockMovementService::class)->distributeIncoming(
            $this->inventory, StockMovement::REASON_PURCHASE, 4, userId: $this->user->id,
            batch: $this->incomingBatch(4),
            branchAllocations: [['branch_id' => $this->branch->id, 'quantity' => 4]],
        );
        $this->assertStock(1);
    }

    public function test_manual_exit_and_signed_adjustment_accept_negative_result(): void
    {
        $batch = $this->batch(0);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'OUT', 'reason' => 'DAMAGED',
            'quantity' => 3, 'manual_batches' => [['id' => $batch->id, 'quantity' => 3]],
        ])->assertSessionHasNoErrors();
        $this->assertStock(-3);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'ADJUSTMENT',
            'reason' => 'INVENTORY_DIFFERENCE', 'quantity' => -2,
        ])->assertSessionHasNoErrors();
        $this->assertStock(-5);
    }

    public function test_batch_edit_accepts_negative_quantity_and_details_include_empty_and_negative_batches(): void
    {
        $batch = $this->batch(0);
        $emptyBatch = $this->batch(0, 'ZERO-002');
        $this->put(route('inventory.product-batches.update', $batch), ['quantity' => -7, 'status' => 'ACTIVE'])
            ->assertSessionHasNoErrors();
        $this->assertStock(-7);
        $controller = app(BranchInventoryController::class);
        $request = Request::create('/');
        $request->setUserResolver(fn () => $this->user);
        $data = $controller->details($request, $this->inventory->fresh())->getData(true);
        $this->assertEquals(-7, $data['stock']);
        $this->assertEqualsCanonicalizing([$batch->id, $emptyBatch->id], array_column($data['batches'], 'id'));
    }

    public function test_multiple_batches_are_consumed_in_expiration_order_and_inactive_stock_is_preserved(): void
    {
        $first = $this->batch(2);
        $second = $this->batch(4, 'LATER-002');
        $second->update(['expiration_date' => now()->addYear()]);
        $inactive = $this->batch(20, 'INACTIVE-003');
        $inactive->update(['status' => 'INACTIVE']);
        $this->inventory->update(['stock' => 6]);
        $this->sell(9);
        $this->assertStock(-3);
        $this->assertEquals(-3, $first->fresh()->quantity);
        $this->assertEquals(0, $second->fresh()->quantity);
        $this->assertEquals(20, $inactive->fresh()->quantity);
    }

    public function test_accumulated_negative_batch_can_be_corrected_and_still_enforces_unit_precision(): void
    {
        $batch = $this->batch(-1500);
        $this->inventory->update(['stock' => -1500]);
        $this->put(route('inventory.product-batches.update', $batch), ['quantity' => -1499, 'status' => 'ACTIVE'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStock(-1499);
        $this->putJson(route('inventory.product-batches.update', $batch), ['quantity' => -1498.5, 'status' => 'ACTIVE'])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertStock(-1499);
    }

    public function test_invalid_payment_rolls_back_negative_inventory_and_new_batches(): void
    {
        $this->postJson(route('ventas.store'), $this->salePayload(3, 'piece', 0))->assertUnprocessable();
        $this->assertEquals(0, $this->inventory->fresh()->stock);
        $this->assertSame(0, $this->inventory->batches()->count());
        $this->assertSame(0, Sale::count());
        $this->assertSame(0, StockMovement::count());
    }

    public function test_negative_sale_quantities_remain_invalid(): void
    {
        $this->postJson(route('ventas.store'), $this->salePayload(-3))->assertUnprocessable();
        $this->assertSame(0, Sale::count());
    }

    public function test_legacy_return_after_batch_tracking_does_not_disappear_on_next_sale(): void
    {
        $sale = $this->sell(3);
        // Reproduce una venta anterior que solo tenía movimiento general, sin lotes vinculados.
        $sale->stockMovements()->firstOrFail()->batches()->delete();
        $this->inventory->batches()->delete();
        $this->inventory->update(['tracks_batches' => false]);
        $this->incoming(2);
        $this->assertStock(-1);
        app(SaleCancellationService::class)->cancel($sale, $this->user, 'Devolución histórica', [
            ['sale_detail_id' => $sale->details()->firstOrFail()->id, 'quantity' => 3],
        ]);
        $this->assertStock(2);
        $this->sell(1);
        $this->assertStock(1);
    }

    public function test_manual_exit_cannot_consume_a_batch_from_another_product(): void
    {
        $other = BranchProduct::create([
            'branch_id' => Branch::create(['name' => 'Otra', 'slug' => 'otra'])->id,
            'product_id' => $this->inventory->product_id, 'stock' => 0, 'status' => 'active',
        ]);
        $foreign = ProductBatch::create(['branch_product_id' => $other->id, 'lot_number' => 'OTHER-001', 'quantity' => 0, 'status' => 'ACTIVE']);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'OUT', 'reason' => 'DAMAGED',
            'quantity' => 3, 'manual_batches' => [['id' => $foreign->id, 'quantity' => 3]],
        ])->assertSessionHasErrors('stock');
        $this->assertStock(0);
        $this->assertEquals(0, $foreign->fresh()->quantity);
        $this->assertSame(0, StockMovement::count());
    }

    public function test_negative_batches_can_be_found_by_lot_number(): void
    {
        $this->batch(-4, 'NEG-924');
        $ids = app(ProductIdentifierSearch::class)->idsForTerm('NEG-924', new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true));
        $this->assertContains($this->inventory->product_id, $ids->all());
    }

    public function test_replenishment_includes_the_deficit_and_summary_keeps_its_sign(): void
    {
        $this->sell(3);
        $report = app(SalesReplenishmentReportService::class)->build(collect([$this->branch]), ['branch_ids' => [$this->branch->id]]);
        $row = $report['sections']['pedido-tiendas'][0]['rows'][0];
        $this->assertEquals(-3, $row['total_stock']);
        $this->assertGreaterThanOrEqual(3, $row['total_suggested']);
        $summary = app(InventoryReportService::class)->summaryForRows(collect([(object) [
            'product' => 'Producto de prueba', 'quantity' => -3, 'current_stock' => -3,
            'min_stock' => 2, 'expiration_date' => now()->subDay()->toDateString(),
        ]]));
        $this->assertEquals(-3, $summary['total_stock']);
        $this->assertSame(1, $summary['out_of_stock']);
        $this->assertSame(0, $summary['expired_batches']);
    }

    public function test_audit_applies_snapshot_difference_even_when_sales_leave_a_negative_balance(): void
    {
        $batch = $this->batch(5);
        $this->inventory->update(['stock' => 5]);
        $audit = PhysicalCount::create(['branch_id' => $this->branch->id, 'created_by' => $this->user->id, 'name' => 'Prueba', 'status' => 'finalized', 'started_at' => now()]);
        $round = $audit->rounds()->create(['round_number' => 1, 'type' => 'initial', 'scope' => 'all', 'opened_by' => $this->user->id, 'started_at' => now()]);
        app(PhysicalCountSnapshotService::class)->ensureForAudit($audit);
        PhysicalCountEntry::create([
            'physical_count_id' => $audit->id, 'physical_count_round_id' => $round->id,
            'branch_product_id' => $this->inventory->id, 'product_batch_id' => $batch->id,
            'product_id' => $this->inventory->product_id, 'user_id' => $this->user->id,
            'counted_quantity' => 3, 'damaged_quantity' => 0, 'expired_quantity' => 0,
        ]);
        $this->sell(6);
        $this->patch(route('audits.physical-counts.apply-adjustments', $audit))->assertSessionHasNoErrors()->assertRedirect(route('audits.physical-counts.show', $audit));
        $this->assertStock(-3);
        $this->assertSame('applied', $audit->fresh()->status);
    }

    public function test_product_with_negative_balance_cannot_be_removed_from_branch(): void
    {
        $this->inventory->update(['stock' => -2]);
        $product = $this->inventory->product;
        $this->delete(route('inventory.branches.products.destroy', ['branch' => $this->branch->slug, 'product' => $product->id]), ['record_version' => $product->updated_at->toJSON()])
            ->assertSessionHasErrors('product');
        $this->assertNotNull($this->inventory->fresh());
    }

    private function batch(float $quantity, string $lot = 'TEST-001'): ProductBatch
    {
        return ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => $lot,
            'quantity' => $quantity, 'initial_quantity' => max(0, $quantity),
            'received_at' => now()->toDateString(), 'expiration_date' => now()->addMonth()->toDateString(), 'status' => 'ACTIVE',
        ]);
    }

    private function incomingBatch(float $quantity): array
    {
        return ['lot_number' => 'NEW-001', 'quantity' => $quantity, 'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString()];
    }

    private function incoming(float $quantity): void
    {
        app(StockMovementService::class)->move($this->inventory, 'IN', 'PURCHASE', $quantity, userId: $this->user->id, batches: [$this->incomingBatch($quantity)]);
    }

    private function assertStock(float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) $this->inventory->fresh()->stock, 0.000001);
        $this->assertEqualsWithDelta($expected, (float) $this->inventory->batches()->whereIn('status', ['ACTIVE', 'SEASONAL'])->sum('quantity'), 0.000001);
    }

    private function salePayload(float $quantity, string $presentation = 'piece', float $cash = 1000): array
    {
        return [
            'branch_id' => $this->branch->id, 'payment_method_id' => $this->payment->id, 'cash_received' => $cash,
            'items' => [['branch_product_id' => $this->inventory->id, 'product_id' => $this->inventory->product_id, 'quantity' => $quantity, 'unit_price' => 10, 'presentation' => $presentation]],
        ];
    }

    private function sell(float $quantity, string $presentation = 'piece'): Sale
    {
        $this->postJson(route('ventas.store'), $this->salePayload($quantity, $presentation))->assertSuccessful();

        return Sale::latest('id')->firstOrFail();
    }
}
