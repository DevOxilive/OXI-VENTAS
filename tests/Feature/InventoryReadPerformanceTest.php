<?php

namespace Tests\Feature;

use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Http\Middleware\HandleInertiaRequests;
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
use App\Support\LocalDateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventoryReadPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Branch $branch;

    private Category $category;

    private BranchProduct $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $role = Role::create(['name' => 'Inventory read test']);
        $role->permissions()->attach(Permission::firstOrCreate(['name' => 'inventory.branches.view']));
        $this->branch = Branch::create(['name' => 'Read test', 'slug' => 'read-test', 'active' => true]);
        $employee = Employee::create(['first_name' => 'Read', 'last_name' => 'Test', 'email' => 'read@example.test']);
        $this->user = User::factory()->create(['role_id' => $role->id, 'employee_id' => $employee->id, 'is_active' => true]);
        $this->user->branches()->attach($this->branch);
        $this->actingAs($this->user);
        $this->category = Category::create(['name' => 'Lecturas']);
        $this->inventory = $this->createInventory('Producto A', -3);
    }

    public function test_partial_table_request_omits_summary_alerts_and_catalog_queries(): void
    {
        $this->createInventory('Producto B', 8);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get($this->inventoryUrl().'?stock=Agotado', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create($this->inventoryUrl())),
            'X-Inertia-Partial-Component' => 'Inventory/BranchInventory',
            'X-Inertia-Partial-Data' => 'branchProductsDB,filters',
        ]);

        $response->assertOk()->assertJsonPath('props.branchProductsDB.total', 1)
            ->assertJsonPath('props.branchProductsDB.data.0.id', $this->inventory->id)
            ->assertJsonMissingPath('props.inventoryStats')->assertJsonMissingPath('props.inventoryAlerts')
            ->assertJsonMissingPath('props.productsDB')->assertJsonMissingPath('props.categoriesDB');
        $sql = strtolower(collect(DB::getQueryLog())->pluck('query')->implode("\n"));
        $this->assertStringNotContainsString('as total_products', $sql);
        $this->assertStringNotContainsString('limit 300', $sql);
        $this->assertStringNotContainsString('limit 50', $sql);
        DB::disableQueryLog();
    }

    public function test_summary_counts_use_two_aggregate_queries_and_preserve_negative_stock(): void
    {
        $this->createInventory('Producto B', 2, 3);
        $this->user->load(['role.permissions', 'permissions', 'branches']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $payload = app(BranchInventoryController::class)->summary($this->request(), $this->branch)->getData(true);
        $aggregates = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], ' as total_products')
            || str_contains($query['query'], ' as expired_batches'));

        $this->assertCount(2, $aggregates);
        $this->assertEquals(-1, $payload['inventoryStats']['total_stock']);
        $this->assertSame(2, $payload['inventoryStats']['total_products']);
        $this->assertSame(1, $payload['inventoryStats']['out_of_stock']);
        $this->assertSame(1, $payload['inventoryAlerts']['low_stock_products']);
        $this->assertArrayNotHasKey('expired_batches_list', $payload['inventoryAlerts']);
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'limit 50')));
        DB::disableQueryLog();
    }

    public function test_alert_lists_are_loaded_only_when_requested(): void
    {
        ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => 'EXPIRED',
            'quantity' => 2, 'initial_quantity' => 2, 'status' => ProductBatch::STATUS_ACTIVE,
            'expiration_date' => now()->subDay()->toDateString(), 'received_at' => now()->subMonth()->toDateString(),
        ]);

        $payload = app(BranchInventoryController::class)->summary($this->request(['include_alerts' => 1]), $this->branch)->getData(true);

        $this->assertSame(1, $payload['inventoryAlerts']['expired_batches']);
        $this->assertCount(1, $payload['inventoryAlerts']['expired_batches_list']);
        $this->assertSame('EXPIRED', $payload['inventoryAlerts']['expired_batches_list'][0]['lot_number']);
    }

    public function test_realtime_rows_do_not_load_movements_details_or_global_summary(): void
    {
        $this->movement('PURCHASE');
        $payload = app(BranchInventoryController::class)->realtimeSnapshot(
            $this->request(['branch_product_id' => $this->inventory->id]), $this->branch,
        )->getData(true);

        $this->assertEquals(-3, $payload['branchProduct']['stock']);
        $this->assertCount(1, $payload['branchProducts']);
        $this->assertArrayNotHasKey('branchProductDetails', $payload);
        $this->assertArrayNotHasKey('inventoryStats', $payload);
        $this->assertArrayNotHasKey('inventoryAlerts', $payload);
        $this->assertArrayNotHasKey('movements', $payload['branchProduct']);
    }

    public function test_batch_snapshot_has_bounded_queries_and_reports_absent_ids_without_crossing_branches(): void
    {
        $second = $this->createInventory('Producto B', 4);
        $foreignBranch = Branch::create(['name' => 'Otra', 'slug' => 'another-read', 'active' => true]);
        $foreign = BranchProduct::create([
            'branch_id' => $foreignBranch->id, 'product_id' => $second->product_id, 'stock' => 99,
            'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->user->load(['role.permissions', 'permissions', 'branches']);
        $controller = app(BranchInventoryController::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $controller->realtimeSnapshot($this->request(['branch_product_ids' => [$this->inventory->id]]), $this->branch);
        $singleCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        $payload = $controller->realtimeSnapshot($this->request([
            'branch_product_ids' => [$this->inventory->id, $second->id, $foreign->id],
        ]), $this->branch)->getData(true);

        $this->assertSame($singleCount, count(DB::getQueryLog()), 'Las relaciones se consultan una vez por grupo, no por fila.');
        $this->assertCount(2, $payload['branchProducts']);
        $this->assertSame([$foreign->id], $payload['missing_branch_product_ids']);
        DB::disableQueryLog();
    }

    public function test_details_keep_empty_negative_and_inactive_batches_but_paginate_movements(): void
    {
        foreach ([0, -3, 5] as $index => $quantity) {
            ProductBatch::create([
                'branch_product_id' => $this->inventory->id, 'lot_number' => 'LOT-'.$index,
                'quantity' => $quantity, 'initial_quantity' => $quantity,
                'status' => $index === 2 ? ProductBatch::STATUS_INACTIVE : ProductBatch::STATUS_ACTIVE,
            ]);
        }
        for ($index = 0; $index < 32; $index++) {
            $this->movement('PURCHASE');
        }
        $payload = app(BranchInventoryController::class)->details($this->request(), $this->inventory)->getData(true);
        $secondPage = app(BranchInventoryController::class)->details($this->request(['movements_page' => 2]), $this->inventory)->getData(true);

        $this->assertCount(3, $payload['batches']);
        $this->assertEqualsCanonicalizing([0, -3, 5], array_column($payload['batches'], 'quantity'));
        $this->assertCount(25, $payload['movements']);
        $this->assertSame(32, $payload['movements_total']);
        $this->assertSame(32, $payload['movements_pagination']['total']);
        $this->assertTrue($payload['movements_pagination']['has_more']);
        $this->assertCount(7, $secondPage['movements']);
        $this->assertFalse($secondPage['movements_pagination']['has_more']);
        $this->assertSame([], array_intersect(array_column($payload['movements'], 'id'), array_column($secondPage['movements'], 'id')));
    }

    public function test_history_filters_and_summary_cover_all_pages_including_audit_groups(): void
    {
        for ($index = 0; $index < 27; $index++) {
            $this->movement('PURCHASE');
        }
        $this->movement('INVENTORY_DIFFERENCE', 'ADJUSTMENT', 'Resultado de conteo físico');
        $this->movement('INVENTORY_DIFFERENCE', 'ADJUSTMENT', 'Ajuste manual');
        $controller = app(BranchInventoryController::class);
        $purchases = $controller->details($this->request(['movement_group' => 'purchases']), $this->inventory)->getData(true);
        $audits = $controller->details($this->request(['movement_group' => 'audits']), $this->inventory)->getData(true);
        $adjustments = $controller->details($this->request(['movement_group' => 'adjustments']), $this->inventory)->getData(true);

        $this->assertSame(29, $purchases['movements_total']);
        $this->assertCount(25, $purchases['movements']);
        $this->assertSame(27, $purchases['movements_pagination']['total']);
        $this->assertSame([['key' => 'purchases', 'count' => 27]], $purchases['movements_summary']);
        $this->assertSame([['id' => $this->user->id, 'name' => $this->user->name]], $purchases['movements_users']);
        $this->assertSame(1, $audits['movements_pagination']['total']);
        $this->assertSame(1, $adjustments['movements_pagination']['total']);
        $this->assertNotSame($audits['movements'][0]['id'], $adjustments['movements'][0]['id']);
    }

    public function test_details_filter_dates_and_users_before_pagination(): void
    {
        $old = $this->movement('PURCHASE');
        $old->forceFill(['created_at' => now()->subDays(5)])->save();
        $current = $this->movement('PURCHASE');
        $payload = app(BranchInventoryController::class)->details($this->request([
            'movement_date_from' => LocalDateTime::today(), 'movement_date_to' => LocalDateTime::today(),
            'movement_user_id' => $this->user->id,
        ]), $this->inventory)->getData(true);

        $this->assertSame(2, $payload['movements_total']);
        $this->assertSame(1, $payload['movements_pagination']['total']);
        $this->assertSame($current->id, $payload['movements'][0]['id']);
    }

    public function test_batch_details_without_movement_history_do_not_query_the_movement_table(): void
    {
        $this->movement('PURCHASE');
        ProductBatch::create([
            'branch_product_id' => $this->inventory->id, 'lot_number' => 'EMPTY-LOT',
            'quantity' => 0, 'initial_quantity' => 0, 'status' => ProductBatch::STATUS_ACTIVE,
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $payload = app(BranchInventoryController::class)->details($this->request([
            'include_movements' => 0,
        ]), $this->inventory)->getData(true);

        $this->assertCount(1, $payload['batches']);
        $this->assertArrayNotHasKey('movements', $payload);
        $this->assertArrayNotHasKey('movements_summary', $payload);
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($query) => str_contains($query['query'], 'stock_movements')));
        DB::disableQueryLog();
    }

    public function test_history_uses_the_mexico_city_day_when_filtering_utc_timestamps(): void
    {
        $beforeDay = $this->movement('PURCHASE');
        $beforeDay->forceFill(['created_at' => '2026-10-28 05:59:59'])->save();
        $start = $this->movement('PURCHASE');
        $start->forceFill(['created_at' => '2026-10-28 06:00:00'])->save();
        $late = $this->movement('PURCHASE');
        $late->forceFill(['created_at' => '2026-10-29 05:59:59'])->save();
        $nextDay = $this->movement('PURCHASE');
        $nextDay->forceFill(['created_at' => '2026-10-29 06:00:00'])->save();

        $payload = app(BranchInventoryController::class)->details($this->request([
            'movement_date_from' => '2026-10-28', 'movement_date_to' => '2026-10-28',
        ]), $this->inventory)->getData(true);

        $this->assertSame(4, $payload['movements_total']);
        $this->assertSame(2, $payload['movements_pagination']['total']);
        $this->assertSame([$late->id, $start->id], array_column($payload['movements'], 'id'));
    }

    public function test_summary_and_details_enforce_branch_access(): void
    {
        $foreignBranch = Branch::create(['name' => 'Sin acceso', 'slug' => 'forbidden-read', 'active' => true]);
        $foreign = BranchProduct::create([
            'branch_id' => $foreignBranch->id, 'product_id' => $this->inventory->product_id,
            'stock' => 20, 'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
        $this->getJson(route('inventory.branches.inventory.summary', ['branch' => $foreignBranch->id]))->assertForbidden();
        $this->getJson(route('inventory.branch-inventory.details', ['branchProduct' => $foreign->id]))->assertForbidden();
    }

    private function createInventory(string $name, float $stock, float $minimum = 0): BranchProduct
    {
        $product = Product::create([
            'name' => $name, 'category_id' => $this->category->id, 'cost' => 5, 'sale_price' => 10,
            'cost_per_piece' => 5, 'sale_price_per_piece' => 10, 'unit' => 'pza', 'inventory_unit' => 'pza',
            'inventory_quantity_mode' => 'base', 'active' => true,
        ]);

        return BranchProduct::create([
            'branch_id' => $this->branch->id, 'product_id' => $product->id, 'stock' => $stock,
            'min_stock' => $minimum, 'tracks_batches' => true, 'status' => BranchProduct::STATUS_ACTIVE,
        ]);
    }

    private function movement(string $reason, string $type = 'IN', ?string $notes = null): StockMovement
    {
        return StockMovement::create([
            'branch_product_id' => $this->inventory->id, 'user_id' => $this->user->id,
            'type' => $type, 'reason' => $reason, 'quantity' => 1, 'previous_stock' => -4, 'new_stock' => -3, 'notes' => $notes,
        ]);
    }

    private function request(array $params = []): Request
    {
        $request = Request::create('/', 'GET', $params);
        $request->setUserResolver(fn () => $this->user);

        return $request;
    }

    private function inventoryUrl(): string
    {
        return route('inventory.branches.inventory', ['branch' => $this->branch->id]);
    }
}
