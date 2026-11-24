<?php

namespace Tests\Feature;

use App\Http\Controllers\Ventas\SalesController;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class SalesProductGalleryPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_pos_product_payload_includes_the_ordered_gallery_and_keeps_the_primary_image(): void
    {
        $branch = Branch::create(['name' => 'POS', 'slug' => 'pos', 'active' => true]);
        $product = Product::create([
            'name' => 'Producto para POS',
            'category_id' => Category::create(['name' => 'POS'])->id,
            'image' => 'products/frente.jpg',
            'cost' => 10,
            'sale_price' => 15,
            'unit' => 'pza',
            'inventory_unit' => 'pza',
            'cost_per_piece' => 10,
            'sale_price_per_piece' => 15,
            'active' => true,
        ]);
        $firstImage = $product->images()->create(['path' => 'products/frente.jpg', 'position' => 1]);
        $secondImage = $product->images()->create(['path' => 'products/lateral.jpg', 'position' => 2]);
        $branchProduct = BranchProduct::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'stock' => 4,
            'min_stock' => 0,
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);

        $branchProduct->load(['product.images', 'product.barcodes', 'product.category']);
        $method = new ReflectionMethod(SalesController::class, 'mapBranchProduct');
        $payload = $method->invoke(new SalesController, $branchProduct, false);

        $this->assertSame(route('inventory.products.image', $product), $payload['image']);
        $this->assertSame([$firstImage->id, $secondImage->id], array_column($payload['images'], 'id'));
        $this->assertSame([1, 2], array_column($payload['images'], 'position'));
        $this->assertSame(
            route('inventory.products.images.show', ['product' => $product, 'productImage' => $firstImage]),
            $payload['images'][0]['url'],
        );
    }
}
