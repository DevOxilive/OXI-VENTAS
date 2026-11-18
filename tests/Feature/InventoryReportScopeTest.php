<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryReportScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_lots_report_only_returns_batches_belonging_to_the_selected_branch(): void
    {
        $selectedBranch = Branch::create(['name' => 'Sucursal consultada', 'slug' => 'consultada', 'active' => true]);
        $otherBranch = Branch::create(['name' => 'Otra sucursal', 'slug' => 'otra-sucursal', 'active' => true]);

        $selectedBatch = $this->createBatch($selectedBranch, 'LOTE-SUCURSAL');
        $this->createBatch($otherBranch, 'LOTE-AJENO');

        $rows = app(InventoryReportService::class)->rows($selectedBranch, [
            'report' => 'dashboard',
        ]);

        $this->assertSame([$selectedBatch->id], $rows->pluck('id')->all());
        $this->assertSame(['LOTE-SUCURSAL'], $rows->pluck('lot_number')->all());
    }

    public function test_negative_stock_filter_returns_only_negative_lots_from_the_selected_branch(): void
    {
        $selectedBranch = Branch::create(['name' => 'Sucursal consultada', 'slug' => 'consultada', 'active' => true]);
        $otherBranch = Branch::create(['name' => 'Otra sucursal', 'slug' => 'otra-sucursal', 'active' => true]);

        $negativeBatch = $this->createBatch($selectedBranch, 'LOTE-NEGATIVO', -2);
        $this->createBatch($selectedBranch, 'LOTE-POSITIVO', 3);
        $this->createBatch($otherBranch, 'LOTE-AJENO-NEGATIVO', -4);

        $rows = app(InventoryReportService::class)->rows($selectedBranch, [
            'report' => 'dashboard',
            'status' => 'negative_stock',
        ]);

        $this->assertSame([$negativeBatch->id], $rows->pluck('id')->all());
        $this->assertSame('Stock negativo', $rows->first()->status_label);
    }

    private function createBatch(Branch $branch, string $lotNumber, float $quantity = 5): ProductBatch
    {
        $category = Category::firstOrCreate(['name' => 'Abarrotes']);
        $product = Product::create([
            'name' => 'Producto '.$lotNumber,
            'category_id' => $category->id,
            'cost' => 10,
            'cost_per_piece' => 10,
            'sale_price' => 15,
            'sale_price_per_piece' => 15,
            'unit' => 'pza',
            'inventory_unit' => 'pza',
            'inventory_quantity_mode' => 'base',
            'active' => true,
        ]);
        $branchProduct = BranchProduct::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => $quantity,
            'min_stock' => 1,
            'tracks_batches' => true,
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);

        return ProductBatch::create([
            'branch_product_id' => $branchProduct->id,
            'lot_number' => $lotNumber,
            'quantity' => $quantity,
            'initial_quantity' => max(0, $quantity),
            'status' => ProductBatch::STATUS_ACTIVE,
            'received_at' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
        ]);
    }
}
