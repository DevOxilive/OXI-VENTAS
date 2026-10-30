<?php

namespace Tests\Feature;

use App\Events\InventoryStockUpdated;
use App\Events\RealtimeActivityLogged;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class InventoryMovementSafetyTest extends TestCase
{
    use RefreshDatabase;

    private BranchProduct $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null', 'scout.driver' => 'collection']);
        Event::fake([InventoryStockUpdated::class, RealtimeActivityLogged::class]);
        $role = Role::create(['name' => 'Inventory movement safety']);
        foreach (['inventory.branches.stock-in', 'inventory.branches.batches.update'] as $name) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $name]));
        }
        $branch = Branch::create(['name' => 'Inventory safety', 'slug' => 'inventory-safety', 'active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($branch);
        $this->actingAs($user);
        $category = Category::create(['name' => 'Inventory safety']);
        $product = Product::create([
            'name' => 'Safety test product', 'category_id' => $category->id, 'cost' => 5, 'sale_price' => 10,
            'cost_per_piece' => 5, 'sale_price_per_piece' => 10, 'unit' => 'pza',
            'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'active' => true,
        ]);
        $this->inventory = BranchProduct::create([
            'branch_id' => $branch->id, 'product_id' => $product->id, 'stock' => 0,
            'min_stock' => 0, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
    }

    public function test_an_incoming_form_cannot_restock_a_product_retired_after_it_was_opened(): void
    {
        $this->inventory->update(['status' => BranchProduct::STATUS_INACTIVE]);
        $this->post(route('inventory.stock-movements.store'), [
            'branch_product_id' => $this->inventory->id, 'type' => 'IN', 'reason' => 'PURCHASE', 'quantity' => 5,
            'batches' => [$this->incomingBatch()],
        ])->assertSessionHasErrors('stock');

        $this->assertSame(0, $this->inventory->movements()->count());
        $this->assertSame(0, $this->inventory->batches()->count());
        $this->assertEquals(0, $this->inventory->fresh()->stock);
    }

    public function test_the_service_rechecks_the_branch_status_under_its_lock(): void
    {
        $staleInventory = $this->inventory->fresh();
        $this->inventory->update(['status' => BranchProduct::STATUS_INACTIVE]);
        $this->expectException(InvalidArgumentException::class);
        app(StockMovementService::class)->move($staleInventory, 'IN', 'PURCHASE', 5, batches: [$this->incomingBatch()]);
    }

    public function test_old_batch_forms_cannot_add_stock_to_a_retired_branch_assignment(): void
    {
        $batch = ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => 'SAFETY-001',
            'quantity' => 0, 'initial_quantity' => 0, 'status' => ProductBatch::STATUS_ACTIVE,
        ]);
        $this->inventory->update(['status' => BranchProduct::STATUS_INACTIVE]);
        $this->put(route('inventory.product-batches.update', $batch), [
            'quantity_delta' => 5, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE],
        ])->assertSessionHasErrors('quantity');

        $this->assertEquals(0, $batch->fresh()->quantity);
        $this->assertSame(0, $this->inventory->movements()->count());
    }

    private function incomingBatch(): array
    {
        return [
            'lot_number' => 'SAFETY-001', 'quantity' => 5,
            'received_at' => now()->toDateString(), 'expiration_date' => now()->addYear()->toDateString(),
        ];
    }
}
