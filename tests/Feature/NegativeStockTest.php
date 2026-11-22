<?php

namespace Tests\Feature;

use App\Events\InventoryStockUpdated;
use App\Events\SaleReturnChanged;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Employee;
use App\Models\EmployeeCreditAccount;
use App\Models\EmployeeCreditCharge;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleCancellation;
use App\Models\SaleCancellationDetail;
use App\Models\StockMovement;
use App\Models\User;
use App\Search\ProductIdentifierSearch;
use App\Search\ProductSearchOptions;
use App\Services\InventoryReportService;
use App\Services\InventoryIncidentReportService;
use App\Services\PhysicalCountSnapshotService;
use App\Services\Reports\SalesReplenishmentReportService;
use App\Services\SaleCancellationService;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
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

    public function test_sale_above_available_stock_preserves_real_lots_and_records_the_pending_deficit_without_lot(): void
    {
        $this->batch(2);
        $this->inventory->update(['stock' => 2]);

        $sale = $this->sell(3);

        $this->assertStock(-1);
        $this->assertSame(0.0, (float) $this->inventory->batches()->where('lot_number', 'TEST-001')->value('quantity'));
        $this->assertEquals(-1, $this->inventory->batches()->whereNull('lot_number')->value('quantity'));
        $this->assertEquals(3, $sale->stockMovements()->firstOrFail()->batches()->sum('quantity'));

        $incidents = app(InventoryIncidentReportService::class)->rows($this->branch);
        $this->assertSame(1, $incidents->total());
        $this->assertEquals(-1, $incidents->items()[0]['pending_quantity']);
        $this->assertSame(0, $incidents->items()[0]['continued_sales_count']);

        $this->incoming(4);
        $this->assertStock(3);
        $this->assertEquals(-1, $this->inventory->batches()->whereNull('lot_number')->value('quantity'));
    }

    public function test_repeated_sales_restock_and_partial_returns_keep_the_deficit(): void
    {
        $this->batch(5);
        $this->inventory->update(['stock' => 5]);
        $sale = $this->sell(5);
        $this->assertStock(0);
        $this->incoming(4);
        $this->assertStock(4);
        $service = app(SaleCancellationService::class);
        $detail = $sale->details()->firstOrFail();
        $service->cancel($sale, $this->user, 'Devolución de prueba', [['sale_detail_id' => $detail->id, 'quantity' => 2]]);
        $this->assertStock(6);
        $service->cancel($sale->fresh(), $this->user, 'Resto de la devolución', [['sale_detail_id' => $detail->id, 'quantity' => 3]]);
        $this->assertStock(9);
        $this->assertSame('cancelled', $sale->fresh()->status);
    }

    public function test_return_records_inventory_financial_effect_and_reprintable_receipt_together(): void
    {
        $this->batch(3);
        $this->inventory->update(['stock' => 3]);
        $sale = $this->sell(2);
        $detail = $sale->details()->firstOrFail();

        $cancellation = app(SaleCancellationService::class)->cancel(
            $sale,
            $this->user,
            'Producto entregado por equivocación',
            [['sale_detail_id' => $detail->id, 'quantity' => 1]],
        );

        $this->assertSame('cash_refund', $cancellation->application_type);
        $this->assertEquals(10, $cancellation->amount);
        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame(1, $cancellation->details()->count());
        $this->assertSame(1, $cancellation->details()->firstOrFail()->returnStockMovement()->count());
        $this->assertStock(2);

        Event::assertDispatched(SaleReturnChanged::class, fn (SaleReturnChanged $event) => $event->action === 'registered'
            && $event->saleId === (int) $sale->id
            && $event->cancellationId === (int) $cancellation->id
            && $event->amount === 10.0
        );

        $this->getJson(route('ventas.returns.ticket', [
            'sale' => $sale->id,
            'cancellation' => $cancellation->id,
        ]))
            ->assertOk()
            ->assertJsonPath('print_job.type', 'sale_return')
            ->assertJsonPath('print_job.folio', 'DEV-'.str_pad((string) $cancellation->id, 6, '0', STR_PAD_LEFT))
            ->assertJsonPath('print_job.original_sale_folio', $sale->folio)
            ->assertJsonPath('print_job.total', 10);
    }

    public function test_invalid_return_leaves_no_partial_records_or_inventory_changes(): void
    {
        $this->batch(2);
        $this->inventory->update(['stock' => 2]);
        $sale = $this->sell(1);
        $detail = $sale->details()->firstOrFail();
        $sale->stockMovements()->firstOrFail()->update(['reason' => StockMovement::REASON_OTHER]);
        $stockBefore = (float) $this->inventory->fresh()->stock;

        try {
            app(SaleCancellationService::class)->cancel(
                $sale,
                $this->user,
                'Intento que debe cancelarse completo',
                [['sale_detail_id' => $detail->id, 'quantity' => 1]],
            );
            $this->fail('La devolución inválida debió rechazarse.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame(0, SaleCancellation::count());
        $this->assertSame(0, SaleCancellationDetail::count());
        $this->assertEquals($stockBefore, (float) $this->inventory->fresh()->stock);
        $this->assertSame('completed', $sale->fresh()->status);
    }

    public function test_credit_return_reduces_the_pending_charge_inside_the_same_operation(): void
    {
        $this->batch(3);
        $this->inventory->update(['stock' => 3]);
        $sale = $this->sell(2);
        $creditMethod = PaymentMethod::create(['name' => 'Crédito empleado', 'active' => true]);
        $sale->update(['payment_method_id' => $creditMethod->id]);
        $account = EmployeeCreditAccount::create([
            'employee_id' => $this->user->employee_id,
            'credit_balance' => 0,
            'active' => true,
        ]);
        $charge = EmployeeCreditCharge::create([
            'employee_credit_account_id' => $account->id,
            'sale_id' => $sale->id,
            'branch_id' => $this->branch->id,
            'amount' => 20,
            'outstanding_amount' => 20,
            'status' => 'open',
        ]);

        $cancellation = app(SaleCancellationService::class)->cancel(
            $sale->fresh(),
            $this->user,
            'Devolución aplicada al adeudo',
            [['sale_detail_id' => $sale->details()->firstOrFail()->id, 'quantity' => 1]],
        );

        $this->assertSame('account_adjustment', $cancellation->application_type);
        $this->assertEquals(20, $cancellation->account_balance_before);
        $this->assertEquals(10, $cancellation->account_balance_after);
        $this->assertEquals(10, $charge->fresh()->outstanding_amount);
        $this->assertSame('open', $charge->fresh()->status);
        $this->assertStock(2);

        app(SaleCancellationService::class)->voidCancellation(
            $sale->fresh(),
            $cancellation,
            $this->user,
            'La devolución se capturó en el ticket equivocado',
        );

        $this->assertEquals(20, $charge->fresh()->outstanding_amount);
        $this->assertSame('open', $charge->fresh()->status);
        $this->assertNotNull($cancellation->fresh()->voided_at);
        $this->assertStock(1);
    }

    public function test_voiding_cash_return_restores_sale_and_keeps_auditable_inventory_countermovement(): void
    {
        $this->batch(2);
        $this->inventory->update(['stock' => 2]);
        $sale = $this->sell(1);
        $service = app(SaleCancellationService::class);
        $cancellation = $service->cancel(
            $sale,
            $this->user,
            'Devolución que se anulará',
            [['sale_detail_id' => $sale->details()->firstOrFail()->id, 'quantity' => 1]],
        );

        $this->assertSame('cancelled', $sale->fresh()->status);
        $this->assertStock(2);

        $voided = $service->voidCancellation(
            $sale->fresh(),
            $cancellation,
            $this->user,
            'La devolución pertenecía a otro ticket',
        );

        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame($this->user->id, $voided->voided_by_user_id);
        $this->assertSame('La devolución pertenecía a otro ticket', $voided->void_reason);
        $this->assertNotNull($voided->voided_at);
        $this->assertNotNull($voided->details->first()->void_stock_movement_id);
        $this->assertSame(0, $sale->activeCancellations()->count());
        $this->assertStock(1);

        Event::assertDispatched(SaleReturnChanged::class, fn (SaleReturnChanged $event) => $event->action === 'voided' && $event->cancellationId === (int) $cancellation->id
        );
    }

    public function test_boxes_and_kilograms_deduct_the_correct_base_quantity(): void
    {
        $this->batch(30);
        $this->inventory->update(['stock' => 30]);
        $this->inventory->product->update(['has_box_presentation' => true, 'pieces_per_box' => 12, 'sale_price_per_box' => 100]);
        $this->sell(2, 'box');
        $this->assertStock(6);
        $this->inventory->product->update(['inventory_unit' => 'kg', 'unit' => 'kg']);
        $this->sell(0.125);
        $this->assertStock(5.875);
    }

    public function test_independent_box_and_piece_prices_charge_and_deduct_the_correct_quantities(): void
    {
        $this->inventory->product->update([
            'has_box_presentation' => true, 'pieces_per_box' => 16,
            'cost_per_piece' => 0, 'sale_price_per_piece' => 18,
            'cost_per_box' => 180, 'sale_price_per_box' => 234,
        ]);
        $this->batch(17);
        $this->inventory->update(['stock' => 17]);
        $pieceSale = $this->sell(1);
        $this->assertEquals(18, $pieceSale->total);
        $boxSale = $this->sell(1, 'box');
        $this->assertEquals(234, $boxSale->total);
        $this->assertStock(0);
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

    public function test_manual_exit_and_negative_adjustment_cannot_exceed_the_selected_lot(): void
    {
        $batch = $this->batch(0);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'OUT', 'reason' => 'DAMAGED',
            'quantity' => 3, 'manual_batches' => [['id' => $batch->id, 'quantity' => 3]],
        ])->assertSessionHasErrors('stock');
        $this->assertStock(0);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'ADJUSTMENT',
            'reason' => 'INVENTORY_DIFFERENCE', 'quantity' => -2,
        ])->assertSessionHasErrors('stock');
        $this->assertStock(0);
    }

    public function test_manual_exit_cannot_take_more_than_its_selected_lot_even_when_another_lot_has_stock(): void
    {
        $selectedBatch = $this->batch(2, 'MANUAL-001');
        $otherBatch = $this->batch(10, 'MANUAL-002');
        $this->inventory->update(['stock' => 12]);

        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id,
            'type' => 'OUT',
            'reason' => 'DAMAGED',
            'quantity' => 3,
            'manual_batches' => [['id' => $selectedBatch->id, 'quantity' => 3]],
        ])->assertSessionHasErrors('stock');

        $this->assertStock(12);
        $this->assertEquals(2, $selectedBatch->fresh()->quantity);
        $this->assertEquals(10, $otherBatch->fresh()->quantity);
    }

    public function test_batch_edit_cannot_reduce_a_lot_below_zero_but_can_correct_a_historical_negative(): void
    {
        $batch = $this->batch(0);
        $this->put(route('inventory.product-batches.update', $batch), ['quantity' => -7, 'expected_quantity' => 0, 'status' => 'ACTIVE', 'expected_values' => ['status' => 'ACTIVE']])
            ->assertSessionHasErrors('quantity');
        $this->assertStock(0);

        $batch->update(['quantity' => -7]);
        $this->inventory->update(['stock' => -7]);
        $this->put(route('inventory.product-batches.update', $batch), ['quantity' => 0, 'expected_quantity' => -7, 'status' => 'ACTIVE', 'expected_values' => ['status' => 'ACTIVE']])
            ->assertSessionHasNoErrors();
        $this->assertStock(0);
    }

    public function test_multiple_batches_are_consumed_in_expiration_order_and_inactive_stock_is_preserved(): void
    {
        $first = $this->batch(2);
        $second = $this->batch(4, 'LATER-002');
        $second->update(['expiration_date' => now()->addYear()]);
        $inactive = $this->batch(20, 'INACTIVE-003');
        $inactive->update(['status' => 'INACTIVE']);
        $this->inventory->update(['stock' => 6]);
        $this->sell(6);
        $this->assertStock(0);
        $this->assertEquals(0, $first->fresh()->quantity);
        $this->assertEquals(0, $second->fresh()->quantity);
        $this->assertEquals(20, $inactive->fresh()->quantity);
    }

    public function test_accumulated_negative_batch_can_be_corrected_and_still_enforces_unit_precision(): void
    {
        $batch = $this->batch(-1500);
        $this->inventory->update(['stock' => -1500]);
        $this->put(route('inventory.product-batches.update', $batch), ['quantity' => -1499, 'expected_quantity' => -1500, 'status' => 'ACTIVE', 'expected_values' => ['status' => 'ACTIVE']])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertStock(-1499);
        $this->putJson(route('inventory.product-batches.update', $batch), ['quantity' => -1498.5, 'expected_quantity' => -1499, 'status' => 'ACTIVE', 'expected_values' => ['status' => 'ACTIVE']])
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
        $this->batch(3);
        $this->inventory->update(['stock' => 3]);
        $sale = $this->sell(3);
        // Reproduce una venta anterior que solo tenía movimiento general, sin lotes vinculados.
        $sale->stockMovements()->firstOrFail()->batches()->delete();
        $this->inventory->batches()->delete();
        $this->inventory->update(['tracks_batches' => false]);
        $this->incoming(2);
        $this->assertStock(2);
        app(SaleCancellationService::class)->cancel($sale, $this->user, 'Devolución histórica', [
            ['sale_detail_id' => $sale->details()->firstOrFail()->id, 'quantity' => 3],
        ]);
        $this->assertStock(5);
        $this->sell(1);
        $this->assertStock(4);
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
        $this->batch(-3);
        $this->inventory->update(['stock' => -3]);
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

    public function test_audit_applies_snapshot_difference_after_a_sale(): void
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
        $this->sell(5);
        $this->patch(route('audits.physical-counts.apply-adjustments', $audit))->assertSessionHasNoErrors()->assertRedirect(route('audits.physical-counts.show', $audit));
        $this->assertStock(-2);
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
