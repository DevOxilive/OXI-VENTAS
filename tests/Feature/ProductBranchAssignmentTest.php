<?php

namespace Tests\Feature;

use App\Events\ProductChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ProductBranchAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_cannot_be_removed_from_branch_when_it_has_stock(): void
    {
        Event::fake([ProductChanged::class, RealtimeActivityLogged::class]);

        [$user, $branch, $product] = $this->productContext(['stock' => 5]);

        $this->actingAs($user)
            ->delete(route('inventory.branches.products.destroy', [
                'branch' => $branch->slug,
                'product' => $product->id,
            ]), ['record_version' => $product->updated_at->toJSON()])
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_product_cannot_be_removed_from_branch_when_it_has_active_batches(): void
    {
        Event::fake([ProductChanged::class, RealtimeActivityLogged::class]);

        [$user, $branch, $product, $branchProduct] = $this->productContext(['stock' => 0]);

        ProductBatch::create([
            'branch_product_id' => $branchProduct->id,
            'lot_number' => 'LOT-001',
            'expiration_date' => now()->addMonth()->toDateString(),
            'initial_quantity' => 0,
            'quantity' => 0,
            'received_at' => now()->toDateString(),
            'status' => ProductBatch::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->delete(route('inventory.branches.products.destroy', [
                'branch' => $branch->slug,
                'product' => $product->id,
            ]), ['record_version' => $product->updated_at->toJSON()])
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('branch_products', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'deleted_at' => null,
        ]);
    }

    public function test_updating_product_restores_soft_deleted_branch_assignment_without_duplicate(): void
    {
        Event::fake([ProductChanged::class, RealtimeActivityLogged::class]);

        $role = Role::create(['name' => 'Inventario']);
        $permission = Permission::create(['name' => 'inventory.products.update']);
        $role->permissions()->attach($permission);

        $branchA = Branch::create([
            'name' => 'Ajusco',
            'slug' => 'ajusco',
            'active' => true,
        ]);
        $branchB = Branch::create([
            'name' => 'Diana',
            'slug' => 'diana',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $user->branches()->attach([$branchA->id, $branchB->id]);

        $category = Category::create(['name' => 'Refrescos']);
        $product = Product::create([
            'name' => 'Sidral Mundet 600 ml',
            'category_id' => $category->id,
            'cost' => 10,
            'sale_price' => 15,
            'unit' => 'pieza',
            'active' => true,
        ]);

        BranchProduct::create([
            'branch_id' => $branchB->id,
            'product_id' => $product->id,
            'stock' => 0,
            'min_stock' => 5,
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $deletedBranchProduct = BranchProduct::create([
            'branch_id' => $branchA->id,
            'product_id' => $product->id,
            'stock' => 0,
            'min_stock' => 5,
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $deletedBranchProduct->delete();

        $this->actingAs($user)
            ->put(route('inventory.branches.products.update', [
                'branch' => $branchB->slug,
                'product' => $product->id,
            ]), [
                'barcodes' => [],
                'unit' => 'pieza',
                'name' => 'Sidral Mundet 600 ml actualizado',
                'min_stock' => 7,
                'category_id' => $category->id,
                'cost' => 11,
                'sale_price' => 16,
                'entry_date' => now()->toDateString(),
                'active' => true,
                'branch_ids' => [$branchA->id, $branchB->id],
                'record_version' => $product->updated_at->toJSON(),
            ])
            ->assertRedirect();

        $this->assertSame(2, BranchProduct::withTrashed()
            ->where('product_id', $product->id)
            ->count());

        $this->assertDatabaseHas('branch_products', [
            'id' => $deletedBranchProduct->id,
            'branch_id' => $branchA->id,
            'product_id' => $product->id,
            'status' => BranchProduct::STATUS_ACTIVE,
            'deleted_at' => null,
        ]);
    }

    private function productContext(array $branchProductOverrides = []): array
    {
        $role = Role::create(['name' => 'Inventario']);
        $permission = Permission::create(['name' => 'inventory.products.delete']);
        $role->permissions()->attach($permission);

        $branch = Branch::create([
            'name' => 'Ajusco',
            'slug' => 'ajusco',
            'active' => true,
        ]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        $user->branches()->attach($branch->id);

        $category = Category::create(['name' => 'Vinos']);
        $product = Product::create([
            'name' => 'Casillero del Diablo Cabernet Sauvignon 750 ml',
            'category_id' => $category->id,
            'cost' => 100,
            'sale_price' => 150,
            'unit' => 'pieza',
            'active' => true,
        ]);
        $branchProduct = BranchProduct::create(array_merge([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 0,
            'min_stock' => 0,
            'status' => BranchProduct::STATUS_ACTIVE,
        ], $branchProductOverrides));

        return [$user, $branch, $product, $branchProduct];
    }
}
