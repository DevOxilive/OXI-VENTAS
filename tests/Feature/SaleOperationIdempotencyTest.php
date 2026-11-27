<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SaleOperationIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Branch $branch;
    private BranchProduct $branchProduct;
    private PaymentMethod $paymentMethod;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        $role = Role::create(['name' => 'Sale operation test']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'sales.create']));
        $this->branch = Branch::create(['name' => 'Sucursal prueba', 'slug' => 'sale-operation-test', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Venta', 'last_name' => 'Prueba', 'email' => 'sale-operation@example.test']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'is_active' => true]);
        $this->user->branches()->attach($this->branch);
        $this->actingAs($this->user);
        $category = Category::create(['name' => 'Pruebas de venta']);
        $product = Product::create([
            'name' => 'Producto POS', 'category_id' => $category->id, 'cost' => 5, 'sale_price' => 10,
            'cost_per_piece' => 5, 'sale_price_per_piece' => 10, 'unit' => 'pza', 'inventory_unit' => 'pza',
            'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->branchProduct = BranchProduct::create([
            'branch_id' => $this->branch->id, 'product_id' => $product->id, 'stock' => 10,
            'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->paymentMethod = PaymentMethod::create(['name' => 'Efectivo', 'active' => true]);
    }

    public function test_same_key_replays_the_confirmed_sale_without_creating_another_one(): void
    {
        $key = (string) Str::uuid();
        $first = $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload());
        $first->assertSuccessful()->assertJsonPath('sale_folio', 'V-000001');

        $retry = $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload());
        $retry->assertSuccessful()->assertHeader('Idempotency-Replayed', 'true')->assertJsonPath('sale_folio', 'V-000001');

        $this->assertSame(1, Sale::count());
        $this->assertDatabaseCount('sale_operations', 1);
    }

    public function test_same_key_with_different_sale_data_is_rejected(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload())->assertSuccessful();
        $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload(quantity: 2))
            ->assertConflict();

        $this->assertSame(1, Sale::count());
    }

    public function test_invalid_sale_does_not_reserve_the_operation_key(): void
    {
        $key = (string) Str::uuid();
        $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload(cashReceived: 1))
            ->assertUnprocessable();
        $this->assertDatabaseCount('sale_operations', 0);

        $this->withHeader('Idempotency-Key', $key)->postJson(route('ventas.store'), $this->payload())
            ->assertSuccessful();
        $this->assertSame(1, Sale::count());
    }

    public function test_operation_and_sale_are_rolled_back_together_when_the_controller_fails(): void
    {
        $this->branchProduct->product->update(['inventory_quantity_mode' => 'legacy_presentation']);

        $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson(route('ventas.store'), $this->payload())
            ->assertUnprocessable();

        $this->assertSame(0, Sale::count());
        $this->assertDatabaseCount('sale_operations', 0);
    }

    public function test_bulk_sales_are_rounded_to_the_nearest_half_peso_without_changing_weight(): void
    {
        $this->branchProduct->product->update([
            'unit' => 'kg',
            'inventory_unit' => 'kg',
            'sale_price' => 91.24,
            'sale_price_per_piece' => 91.24,
        ]);

        $this->postJson(route('ventas.store'), [
            'branch_id' => $this->branch->id,
            'payment_method_id' => $this->paymentMethod->id,
            'cash_received' => 20,
            'items' => [[
                'branch_product_id' => $this->branchProduct->id,
                'product_id' => $this->branchProduct->product_id,
                'quantity' => 0.113,
                'presentation' => 'piece',
                'unit_price' => 0,
            ]],
        ])->assertSuccessful();

        $detail = Sale::latest('id')->firstOrFail()->details()->firstOrFail();

        $this->assertSame('0.113', $detail->quantity);
        $this->assertSame('10.31', $detail->calculated_subtotal);
        $this->assertSame('0.19', $detail->rounding_adjustment);
        $this->assertSame('10.50', $detail->subtotal);
    }

    private function payload(int $quantity = 1, float $cashReceived = 100): array
    {
        return [
            'branch_id' => $this->branch->id,
            'payment_method_id' => $this->paymentMethod->id,
            'cash_received' => $cashReceived,
            'items' => [[
                'branch_product_id' => $this->branchProduct->id,
                'product_id' => $this->branchProduct->product_id,
                'quantity' => $quantity,
                'presentation' => 'piece',
                'unit_price' => 10,
            ]],
        ];
    }
}
