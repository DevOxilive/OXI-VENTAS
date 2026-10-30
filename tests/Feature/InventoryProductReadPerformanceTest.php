<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventoryProductReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $role = Role::create(['name' => 'Inventory read performance']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.products.view']));
        $this->branch = Branch::create(['name' => 'Inventory read', 'slug' => 'inventory-read', 'active' => true]);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($this->branch);
        $this->actingAs($user);
    }

    public function test_requesting_only_filters_does_not_load_products_or_catalogs(): void
    {
        $productReads = 0;
        Product::retrieved(function () use (&$productReads) {
            $productReads++;
        });

        $this->get($this->url(), $this->partialHeaders('filters'))
            ->assertOk()
            ->assertJsonPath('component', 'Inventory/Home')
            ->assertJsonMissingPath('props.productsDB')
            ->assertJsonMissingPath('props.categoriesDB')
            ->assertJsonMissingPath('props.productDepartmentsDB')
            ->assertJsonMissingPath('props.branchesDB');

        $this->assertSame(0, $productReads);
    }

    public function test_partial_product_page_preserves_totals_and_excludes_static_catalogs(): void
    {
        $category = Category::create(['name' => 'Read test']);
        for ($i = 0; $i < 30; $i++) {
            $product = Product::create([
                'name' => 'Producto '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'category_id' => $category->id, 'cost' => 5, 'sale_price' => 10,
                'unit' => 'pza', 'inventory_unit' => 'pza', 'active' => true,
            ]);
            BranchProduct::create([
                'branch_id' => $this->branch->id, 'product_id' => $product->id,
                'stock' => 0, 'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE,
            ]);
        }

        $this->get($this->url().'?page=2&per_page=25', $this->partialHeaders('productsDB,filters'))
            ->assertOk()
            ->assertJsonPath('props.productsDB.total', 30)
            ->assertJsonPath('props.productsDB.current_page', 2)
            ->assertJsonCount(5, 'props.productsDB.data')
            ->assertJsonMissingPath('props.categoriesDB')
            ->assertJsonMissingPath('props.branchesDB');
    }

    public function test_product_partial_page_still_enforces_branch_access(): void
    {
        $foreign = Branch::create(['name' => 'Other', 'slug' => 'other-inventory-read', 'active' => true]);
        $this->getJson(route('inventory.branches.products.index', $foreign->slug), $this->partialHeaders('productsDB,filters'))
            ->assertForbidden();
    }

    private function url(): string
    {
        return route('inventory.branches.products.index', $this->branch->slug);
    }

    private function partialHeaders(string $fields): array
    {
        return [
            'X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Inventory/Home',
            'X-Inertia-Partial-Data' => $fields,
            'X-Inertia-Version' => (new HandleInertiaRequests)->version(Request::create($this->url())),
        ];
    }
}
