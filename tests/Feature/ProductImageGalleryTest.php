<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageGalleryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        Storage::fake('local');
        $role = Role::create(['name' => 'Catálogo']);
        foreach (['inventory.products.create', 'inventory.products.update', 'inventory.products.view', 'inventory.products.manage-pricing'] as $permission) {
            $role->permissions()->attach(Permission::firstOrCreate(['name' => $permission]));
        }

        $this->branch = Branch::create(['name' => 'Galería', 'slug' => 'galeria', 'active' => true]);
        $this->category = Category::create(['name' => 'Pruebas de galería']);
        $user = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        $user->branches()->attach($this->branch);
        $this->actingAs($user);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Producto con imágenes',
            'category_id' => $this->category->id,
            'inventory_unit' => 'pza',
            'has_box_presentation' => false,
            'has_package_presentation' => false,
            'cost_per_piece' => 10,
            'sale_price_per_piece' => 15,
            'piece_pricing_mode' => 'manual',
            'box_pricing_mode' => 'manual',
            'min_stock' => 0,
            'entry_date' => now()->toDateString(),
            'active' => true,
        ], $overrides);
    }

    public function test_product_creation_keeps_all_gallery_images_in_the_requested_order(): void
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'images' => [
                'new-front' => UploadedFile::fake()->image('frente.jpg'),
                'new-side' => UploadedFile::fake()->image('lateral.png'),
            ],
            'image_order' => ['new:new-front', 'new:new-side'],
        ]))->assertSessionHasNoErrors();

        $product = Product::firstOrFail()->fresh('images');

        $this->assertCount(2, $product->images);
        $this->assertSame($product->images->first()->path, $product->image);
        $this->assertSame([1, 2], $product->images->pluck('position')->all());
        $product->images->each(fn ($image) => Storage::disk('local')->assertExists($image->path));
    }

    public function test_product_can_be_created_without_images(): void
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto sin imágenes',
            'has_gallery_images' => true,
            'image_order' => [],
        ]))->assertSessionHasNoErrors();

        $product = Product::where('name', 'Producto sin imágenes')->firstOrFail();

        $this->assertNull($product->image);
        $this->assertCount(0, $product->images);
    }

    public function test_product_can_be_created_with_a_single_image(): void
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto con una imagen',
            'images' => ['new-front' => UploadedFile::fake()->image('frente.jpg')],
            'image_order' => ['new:new-front'],
            'has_gallery_images' => true,
        ]))->assertSessionHasNoErrors();

        $product = Product::where('name', 'Producto con una imagen')->firstOrFail();

        $this->assertCount(1, $product->images);
        $this->assertSame($product->images->first()->path, $product->image);
        Storage::disk('local')->assertExists($product->image);
    }

    public function test_product_accepts_all_eight_gallery_positions(): void
    {
        $images = [];
        $order = [];

        foreach (range(1, 8) as $position) {
            $key = "new-{$position}";
            $images[$key] = UploadedFile::fake()->image("{$position}.jpg");
            $order[] = "new:{$key}";
        }

        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto con ocho imágenes',
            'images' => $images,
            'image_order' => $order,
            'has_gallery_images' => true,
        ]))->assertSessionHasNoErrors();

        $product = Product::where('name', 'Producto con ocho imágenes')->firstOrFail();

        $this->assertCount(8, $product->images);
        $this->assertSame(range(1, 8), $product->images->pluck('position')->all());
    }

    public function test_product_rejects_a_ninth_image_and_an_image_over_two_megabytes(): void
    {
        $tooManyImages = [];
        foreach (range(1, 9) as $position) {
            $tooManyImages["new-{$position}"] = UploadedFile::fake()->image("{$position}.jpg");
        }

        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto con nueve imágenes',
            'images' => $tooManyImages,
        ]))->assertSessionHasErrors('images');

        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto con imagen pesada',
            'images' => ['new-heavy' => UploadedFile::fake()->image('pesada.jpg')->size(2049)],
            'image_order' => ['new:new-heavy'],
            'has_gallery_images' => true,
        ]))->assertSessionHasErrors('images.new-heavy');
    }

    public function test_failed_gallery_order_removes_files_that_were_already_stored(): void
    {
        $this->post(route('inventory.branches.products.store', $this->branch->slug), $this->payload([
            'name' => 'Producto con orden inválido',
            'images' => ['new-front' => UploadedFile::fake()->image('frente.jpg')],
            'image_order' => ['new:missing-file'],
            'has_gallery_images' => true,
        ]))->assertSessionHasErrors('image_order');

        Storage::disk('local')->assertDirectoryEmpty('products');
        $this->assertDatabaseMissing('products', ['name' => 'Producto con orden inválido']);
    }

    public function test_product_update_reorders_keeps_and_removes_gallery_images(): void
    {
        $product = Product::create([
            'name' => 'Producto existente',
            'category_id' => $this->category->id,
            'image' => 'products/primera.jpg',
            'cost' => 10,
            'sale_price' => 15,
            'unit' => 'pza',
            'inventory_unit' => 'pza',
            'cost_per_piece' => 10,
            'sale_price_per_piece' => 15,
            'active' => true,
        ]);
        Storage::disk('local')->put('products/primera.jpg', 'one');
        Storage::disk('local')->put('products/segunda.jpg', 'two');
        $first = $product->images()->create(['path' => 'products/primera.jpg', 'position' => 1]);
        $second = $product->images()->create(['path' => 'products/segunda.jpg', 'position' => 2]);
        BranchProduct::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'stock' => 0,
            'min_stock' => 0,
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);

        $this->put(route('inventory.branches.products.update', [$this->branch->slug, $product]), $this->payload([
            'name' => $product->name,
            'image_order' => ["stored:{$second->id}", 'new:new-back'],
            'images' => ['new-back' => UploadedFile::fake()->image('posterior.webp')],
            'record_version' => $product->updated_at->toJSON(),
            'catalog_version' => $product->catalog_version,
        ]))->assertSessionHasNoErrors();

        $product->refresh()->load('images');

        $this->assertCount(2, $product->images);
        $this->assertSame('products/segunda.jpg', $product->images->first()->path);
        $this->assertSame($product->images->first()->path, $product->image);
        Storage::disk('local')->assertMissing($first->path);
        Storage::disk('local')->assertExists($product->images->last()->path);
    }
}
