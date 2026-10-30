<?php

namespace Tests\Feature;

use App\Events\ProductChanged;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\TestCase;

class InventoryProductConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null', 'scout.driver' => 'collection']);
        $role = Role::firstOrCreate(['name' => 'Inventario prueba concurrente']);
        foreach (['inventory.products.create', 'inventory.products.update', 'inventory.products.delete', 'inventory.products.view', 'inventory.products.manage-pricing', 'branches.access-all'] as $name) {
            $role->permissions()->syncWithoutDetaching([Permission::firstOrCreate(['name' => $name])->id]);
        }

        $this->branch = Branch::create(['name' => 'San Cristóbal prueba', 'slug' => 'sancristobal-prueba', 'active' => true]);
        $this->category = Category::create(['name' => 'Catálogo concurrente']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $this->user->branches()->attach($this->branch);
        $this->actingAs($this->user);
    }

    public function test_initial_listing_and_snapshot_include_both_versions_and_snapshot_can_be_saved(): void
    {
        $product = $this->createProduct();

        $this->get(route('inventory.branches.products.index', $this->branch->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Inventory/Home')
                ->where('productsDB.data.0.record_version', $product->updated_at->toJSON())
                ->where('productsDB.data.0.catalog_version', 1)
            );

        $snapshot = $this->getJson(route('inventory.branches.products.snapshot', [$this->branch->slug, $product->id]))
            ->assertOk()
            ->assertJsonPath('product.record_version', $product->updated_at->toJSON())
            ->assertJsonPath('product.catalog_version', 1)
            ->json('product');

        $this->put($this->updateUrl($product), $this->payload([
            'record_version' => $snapshot['record_version'],
            'catalog_version' => $snapshot['catalog_version'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(2, $product->fresh()->catalog_version);
    }

    public function test_barcode_only_save_rejects_an_older_form_even_with_the_same_timestamp(): void
    {
        $this->freezeTime();
        $product = $this->createProduct();
        $versions = $this->versions($product);

        $this->put($this->updateUrl($product), $this->payload([
            ...$versions,
            'barcodes' => ['7501000000001', '123456789'],
        ]))->assertSessionHasNoErrors();

        $this->assertSame($versions['record_version'], $product->fresh()->updated_at->toJSON());
        $this->assertSame(2, $product->fresh()->catalog_version);

        $this->put($this->updateUrl($product), $this->payload([
            ...$versions,
            'barcodes' => ['7501000000001', '987654321'],
        ]))->assertSessionHasErrors('catalog_version');

        $this->assertDatabaseHas('barcodes', ['product_id' => $product->id, 'code' => '123456789', 'deleted_at' => null]);
        $this->assertDatabaseMissing('barcodes', ['product_id' => $product->id, 'code' => '987654321']);
        $this->assertSame(2, $product->fresh()->catalog_version);
    }

    public function test_branch_only_save_also_rejects_an_older_form(): void
    {
        $product = $this->createProduct();
        $versions = $this->versions($product);
        $otherBranch = Branch::create(['name' => 'Otra sucursal', 'slug' => 'otra-concurrente', 'active' => true]);

        $this->put($this->updateUrl($product), $this->payload([
            ...$versions,
            'branch_ids' => [$this->branch->id, $otherBranch->id],
        ]))->assertSessionHasNoErrors();

        $this->put($this->updateUrl($product), $this->payload($versions))
            ->assertSessionHasErrors('catalog_version');

        $this->assertDatabaseHas('branch_products', [
            'product_id' => $product->id, 'branch_id' => $otherBranch->id, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
    }

    public function test_catalog_version_is_required_for_update_and_delete(): void
    {
        $product = $this->createProduct();

        $this->put($this->updateUrl($product), $this->payload([
            'record_version' => $product->updated_at->toJSON(),
            'barcodes' => [],
        ]))->assertSessionHasErrors('catalog_version');

        $this->delete(route('inventory.branches.products.destroy', [$this->branch->slug, $product->id]), [
            'record_version' => $product->updated_at->toJSON(),
        ])->assertSessionHasErrors('catalog_version');

        $this->assertDatabaseHas('barcodes', ['product_id' => $product->id, 'code' => '7501000000001', 'deleted_at' => null]);
        $this->assertDatabaseHas('branch_products', ['product_id' => $product->id, 'branch_id' => $this->branch->id, 'deleted_at' => null]);
    }

    public function test_other_product_writers_are_still_protected_by_the_timestamp(): void
    {
        $product = $this->createProduct();
        $versions = $this->versions($product);
        $this->travel(2)->seconds();
        $product->update(['name' => 'Cambio por otro proceso']);

        $this->put($this->updateUrl($product), $this->payload($versions))
            ->assertSessionHasErrors('catalog_version');

        $this->assertSame('Cambio por otro proceso', $product->fresh()->name);
    }

    public function test_retiring_a_branch_rechecks_stock_after_the_product_lock(): void
    {
        $product = $this->createProduct();
        $otherBranch = Branch::create(['name' => 'Sucursal con entrada', 'slug' => 'entrada-concurrente', 'active' => true]);
        $assignment = BranchProduct::create([
            'product_id' => $product->id, 'branch_id' => $otherBranch->id,
            'stock' => 0, 'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);

        $injected = $this->addStockWhenProductIsLocked($product, $assignment);

        try {
            $this->put($this->updateUrl($product), $this->payload($this->versions($product)))
                ->assertSessionHasErrors('branch_ids');
            $this->assertTrue($injected());
        } finally {
            Event::forget('eloquent.retrieved: '.Product::class);
        }

        // La entrada se simula dentro de la misma conexión y se revierte con el rechazo.
        $this->assertSame(BranchProduct::STATUS_ACTIVE, $assignment->fresh()->status);
        $this->assertSame(1, $product->fresh()->catalog_version);
    }

    public function test_global_delete_rechecks_stock_after_the_product_lock(): void
    {
        $product = $this->createProduct();
        $assignment = BranchProduct::where('product_id', $product->id)->firstOrFail();
        $injected = $this->addStockWhenProductIsLocked($product, $assignment);

        try {
            $this->delete(route('inventory.branches.products.destroy', [$this->branch->slug, $product->id]), [
                ...$this->versions($product),
                'delete_globally' => true,
            ])->assertSessionHasErrors('product');
            $this->assertTrue($injected());
        } finally {
            Event::forget('eloquent.retrieved: '.Product::class);
        }

        $this->assertFalse($product->fresh()->trashed());
        $this->assertFalse($assignment->fresh()->trashed());
    }

    public function test_realtime_failure_does_not_turn_a_confirmed_save_into_an_error(): void
    {
        $product = $this->createProduct();
        Event::listen(ProductChanged::class, function (): void {
            throw new RuntimeException('Fallo de notificación simulado');
        });

        $this->put($this->updateUrl($product), $this->payload([
            ...$this->versions($product),
            'barcodes' => ['7501000000001', '123456789'],
        ]))->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertDatabaseHas('barcodes', ['product_id' => $product->id, 'code' => '123456789', 'deleted_at' => null]);
        $this->assertSame(2, $product->fresh()->catalog_version);
    }

    private function addStockWhenProductIsLocked(Product $product, BranchProduct $assignment): \Closure
    {
        $levelBeforeRequest = DB::transactionLevel();
        $injected = false;
        Event::listen('eloquent.retrieved: '.Product::class, function (Product $retrieved) use ($product, $assignment, $levelBeforeRequest, &$injected): void {
            if (! $injected && $retrieved->id === $product->id && DB::transactionLevel() > $levelBeforeRequest) {
                $injected = true;
                BranchProduct::whereKey($assignment->id)->update(['stock' => 5]);
            }
        });

        return function () use (&$injected): bool {
            return $injected;
        };
    }

    private function createProduct(): Product
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        return Product::latest('id')->firstOrFail();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Producto colaborativo', 'category_id' => $this->category->id,
            'barcodes' => ['7501000000001'], 'branch_ids' => [$this->branch->id],
            'inventory_unit' => 'pza', 'has_box_presentation' => false,
            'cost_per_piece' => 10, 'sale_price_per_piece' => 15,
            'piece_pricing_mode' => 'manual', 'min_stock' => 0,
            'entry_date' => now()->toDateString(), 'active' => true,
        ], $overrides);
    }

    private function versions(Product $product): array
    {
        return ['record_version' => $product->updated_at->toJSON(), 'catalog_version' => $product->catalog_version];
    }

    private function updateUrl(Product $product): string
    {
        return route('inventory.branches.products.update', [$this->branch->slug, $product->id]);
    }
}
