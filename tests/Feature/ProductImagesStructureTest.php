<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImagesStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_images_are_kept_in_their_catalog_order(): void
    {
        $product = Product::create([
            'name' => 'Producto con galería',
            'category_id' => Category::create(['name' => 'Galería'])->id,
            'cost' => 10,
            'sale_price' => 15,
            'unit' => 'pieza',
            'active' => true,
        ]);

        $product->images()->createMany([
            ['path' => 'products/frente.webp', 'position' => 1],
            ['path' => 'products/lateral.webp', 'position' => 2],
        ]);

        $this->assertSame(
            ['products/frente.webp', 'products/lateral.webp'],
            $product->fresh()->images->pluck('path')->all(),
        );
    }
}
