<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseCycle;
use App\Models\Role;
use App\Models\User;
use App\Services\PurchaseCycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class IndependentProductPricingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        config(['broadcasting.default' => 'null']);
        $role = Role::firstOrCreate(['name' => 'Administrador']);
        foreach (['inventory.products.create', 'inventory.products.update', 'inventory.products.view', 'inventory.products.manage-pricing'] as $name) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['name' => $name])->id]);
        }
        $this->branch = Branch::create(['name' => 'Pricing', 'slug' => 'pricing', 'active' => true]);
        $this->category = Category::create(['name' => 'Pricing']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($this->branch);
        $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Cigarro prueba', 'category_id' => $this->category->id,
            'inventory_unit' => 'pza', 'has_box_presentation' => true, 'pieces_per_box' => 16,
            'cost_per_piece' => 0, 'sale_price_per_piece' => 18,
            'cost_per_box' => 180, 'sale_price_per_box' => 234,
            'piece_pricing_mode' => 'manual', 'box_pricing_mode' => 'percentage',
            'piece_margin_percentage' => null, 'box_margin_percentage' => 30,
            'min_stock' => 3, 'entry_date' => now()->toDateString(), 'active' => true,
        ], $overrides);
    }

    private function createProduct(array $overrides = []): Product
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload($overrides))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Product::latest('id')->firstOrFail();
    }

    public function test_zero_piece_cost_manual_sale_and_box_percentage_survive_save_reload_and_edit(): void
    {
        $product = $this->createProduct(['sale_price_per_box' => 999]);
        $this->assertEquals(18, $product->sale_price_per_piece);
        $this->assertEquals(234, $product->sale_price_per_box);
        $this->assertNull($product->margin_percentage);
        $this->getJson(route('inventory.branches.products.snapshot', [$this->branch->slug, $product->id]))
            ->assertOk()->assertJsonPath('product.piece_pricing_mode', 'manual')
            ->assertJsonPath('product.box_pricing_mode', 'percentage')
            ->assertJsonPath('product.box_margin_percentage', '30.0000');

        $this->put(route('inventory.branches.products.update', [$this->branch->slug, $product->id]),
            $this->payload(['sale_price_per_piece' => 20, 'record_version' => $product->updated_at->toJSON(), 'catalog_version' => $product->catalog_version]))
            ->assertSessionHasNoErrors();
        $this->assertEquals(20, $product->fresh()->sale_price_per_piece);
        $this->assertEquals(234, $product->fresh()->sale_price_per_box);
    }

    public function test_each_presentation_uses_its_own_percentage(): void
    {
        $product = $this->createProduct([
            'cost_per_piece' => 10, 'piece_pricing_mode' => 'percentage', 'piece_margin_percentage' => 80,
        ]);
        $this->assertEquals(18, $product->sale_price_per_piece);
        $this->assertEquals(234, $product->sale_price_per_box);
        $this->purchase($product, 'Pieza', 12);
        $this->assertEquals(21.60, $product->fresh()->sale_price_per_piece);
        $this->assertEquals(234, $product->fresh()->sale_price_per_box);
        $this->purchase($product, 'Caja', 200);
        $this->assertEquals(260, $product->fresh()->sale_price_per_box);
        $this->assertEquals(21.60, $product->fresh()->sale_price_per_piece);
    }

    public function test_new_purchases_preserve_manual_prices_and_update_costs(): void
    {
        $product = $this->createProduct(['box_pricing_mode' => 'manual', 'sale_price_per_box' => 250]);
        $this->purchase($product, 'Caja', 200);
        $this->purchase($product, 'Pieza', 10);
        $product->refresh();
        $this->assertEquals(200, $product->cost_per_box);
        $this->assertEquals(10, $product->cost_per_piece);
        $this->assertEquals(250, $product->sale_price_per_box);
        $this->assertEquals(18, $product->sale_price_per_piece);
        $this->assertEquals(18, $product->sale_price);
    }

    public function test_package_purchase_updates_only_its_independent_cost(): void
    {
        $product = $this->createProduct(['cost_per_piece' => 10, 'cost_per_package' => 50, 'cost_per_box' => 180]);
        $this->purchase($product, 'Paquete', 60);
        $product->refresh();

        $this->assertEquals(10, $product->cost_per_piece);
        $this->assertEquals(60, $product->cost_per_package);
        $this->assertEquals(180, $product->cost_per_box);
    }

    public function test_general_purchase_saves_planned_batches_without_creating_branch_stock(): void
    {
        $product = $this->createProduct();
        $product->update(['has_package_presentation' => true, 'pieces_per_package' => 6]);
        $user = auth()->user();
        $cycle = PurchaseCycle::create([
            'folio' => 'CYCLE-BATCH-1',
            'status' => PurchaseCycle::STATUS_OPEN,
            'created_by' => $user->id,
            'opened_at' => now(),
        ]);
        $order = GeneralPurchaseOrder::create([
            'purchase_cycle_id' => $cycle->id,
            'created_by' => $user->id,
            'folio' => 'OCG-BATCH-1',
            'status' => GeneralPurchaseOrder::STATUS_PURCHASING,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'base_unit' => 'pza',
            'requested_quantity' => 18,
        ]);

        app(PurchaseCycleService::class)->saveCapture($order, [
            'purchased_at' => now()->toDateString(),
            'record_version' => $order->updated_at->toJSON(),
            'items' => [[
                'id' => $item->id,
                'purchase_presentation' => 'Paquete',
                'package_quantity' => 3,
                'units_per_package' => 6,
                'purchase_price' => 60,
                'unavailable' => false,
                'batches' => [
                    ['lot_number' => 'lote-a', 'expiration_date' => '2027-01-10', 'quantity' => 1],
                    ['lot_number' => 'LOTE-B', 'expiration_date' => '2027-02-10', 'quantity' => 2],
                ],
            ]],
        ]);

        $this->assertDatabaseHas('general_purchase_order_item_batches', [
            'general_purchase_order_item_id' => $item->id,
            'lot_number' => 'LOTE-A',
            'presentation_quantity' => 1,
            'base_quantity' => 6,
        ]);
        $this->assertDatabaseHas('general_purchase_order_item_batches', [
            'general_purchase_order_item_id' => $item->id,
            'lot_number' => 'LOTE-B',
            'presentation_quantity' => 2,
            'base_quantity' => 12,
        ]);
        $this->assertDatabaseCount('product_batches', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_percentage_requires_positive_cost_and_low_margin_requires_authorization(): void
    {
        $route = route('inventory.branches.products.store', $this->branch->slug);
        $this->post($route, $this->payload(['piece_pricing_mode' => 'percentage', 'piece_margin_percentage' => 30]))
            ->assertSessionHasErrors('piece_margin_percentage');
        $this->post($route, $this->payload(['box_margin_percentage' => 5]))
            ->assertSessionHasErrors('sale_price_per_box');
        $this->createProduct(['box_margin_percentage' => 5, 'allow_low_margin' => true]);
    }

    public function test_inventory_user_cannot_override_manual_prices_or_pricing_modes(): void
    {
        $product = $this->createProduct();
        $role = Role::firstOrCreate(['name' => 'Inventario']);
        $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['name' => 'inventory.products.update'])->id]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($this->branch);
        $this->actingAs($user)->put(route('inventory.branches.products.update', [$this->branch->slug, $product->id]),
            $this->payload([
                'sale_price_per_piece' => 99, 'piece_pricing_mode' => 'percentage', 'piece_margin_percentage' => 90,
                'box_pricing_mode' => 'manual', 'sale_price_per_box' => 999, 'cost_per_box' => 200,
                'box_margin_percentage' => 80, 'record_version' => $product->updated_at->toJSON(),
                'catalog_version' => $product->catalog_version,
            ]))->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('manual', $product->piece_pricing_mode);
        $this->assertSame('percentage', $product->box_pricing_mode);
        $this->assertEquals(30, $product->box_margin_percentage);
        $this->assertEquals(18, $product->sale_price_per_piece);
        $this->assertEquals(260, $product->sale_price_per_box);
    }

    public function test_inventory_user_with_pricing_permission_can_set_manual_prices(): void
    {
        $product = $this->createProduct();
        $role = Role::firstOrCreate(['name' => 'Inventario']);
        foreach (['inventory.products.update', 'inventory.products.manage-pricing'] as $name) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['name' => $name])->id]);
        }
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($this->branch);

        $this->actingAs($user)->put(route('inventory.branches.products.update', [$this->branch->slug, $product->id]),
            $this->payload([
                'sale_price_per_piece' => 25,
                'box_pricing_mode' => 'manual',
                'sale_price_per_box' => 280,
                'record_version' => $product->updated_at->toJSON(),
                'catalog_version' => $product->catalog_version,
            ]))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertEquals(25, $product->sale_price_per_piece);
        $this->assertEquals(280, $product->sale_price_per_box);
    }

    private function purchase(Product $product, string $presentation, float $cost): void
    {
        $order = new GeneralPurchaseOrder;
        $order->setRelation('items', collect([new GeneralPurchaseOrderItem([
            'product_id' => $product->id, 'purchase_presentation' => $presentation, 'purchase_price' => $cost,
        ])]));
        $method = new \ReflectionMethod(PurchaseCycleService::class, 'updateProductCosts');
        $method->invoke(app(PurchaseCycleService::class), $order);
    }
}
