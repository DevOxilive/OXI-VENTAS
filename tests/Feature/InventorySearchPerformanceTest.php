<?php

namespace Tests\Feature;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductDepartment;
use App\Search\DatabaseProductSearch;
use App\Search\ProductSearchDictionary;
use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InventorySearchPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fakeExcept(['eloquent.retrieved: '.Product::class]);
        config(['scout.driver' => 'collection', 'broadcasting.default' => 'null']);
        $this->branch = Branch::create(['name' => 'Búsqueda laboratorio', 'slug' => 'busqueda-laboratorio', 'active' => true]);
        $department = ProductDepartment::create(['name' => 'Bebidas', 'active' => true]);
        $this->category = Category::create(['name' => 'Refrescos', 'product_department_id' => $department->id, 'active' => true]);
    }

    public function test_prefilter_keeps_existing_ranking_for_names_aliases_accents_typos_and_mixed_terms(): void
    {
        $this->product('Coca Cola 600ml', ['search_aliases' => ['fresco', 'té helado']], '0012345678901');
        $this->product('Coca Cola 200ml');
        $this->product('Chocolate amargo');
        $this->product('Café molido');
        $this->seedUnrelatedProducts(600);

        $search = app(DatabaseProductSearch::class);
        foreach (['coc', 'coc 600', 'cocaa', 'chocolatte', 'refres', 'bebidas', 'fresco', 'te helado', 'cafe', '0012345678901', '001'] as $term) {
            $expected = $search->ids($term, $this->searchOptions(false))->all();
            $actual = $search->ids($term, $this->searchOptions(true))->all();
            $this->assertSame($expected, $actual, $term);
        }
    }

    public function test_sql_rejects_unrelated_catalog_before_hydrating_products(): void
    {
        $target = $this->product('Zafiro ultra exclusivo');
        $this->seedUnrelatedProducts(650);
        $retrieved = 0;
        Product::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        try {
            $ids = app(DatabaseProductSearch::class)->ids('zafiro ultra', $this->searchOptions(true));
            $this->assertSame([$target->id], $ids->all());
            $this->assertSame(1, $retrieved);

            $retrieved = 0;
            $this->assertSame([], app(DatabaseProductSearch::class)->ids('zzzzzzzzzzzzz', $this->searchOptions(true))->all());
            $this->assertSame(0, $retrieved);
        } finally {
            Event::forget('eloquent.retrieved: '.Product::class);
        }
    }

    public function test_more_than_five_hundred_matches_are_ranked_without_losing_products(): void
    {
        $this->seedUnrelatedProducts(620, 'Producto amplio');
        $lateExact = $this->product('Producto');

        $expected = app(DatabaseProductSearch::class)->ids('producto', $this->searchOptions(false))->all();
        $actual = app(DatabaseProductSearch::class)->ids('producto', $this->searchOptions(true))->all();

        $this->assertCount(621, $actual);
        $this->assertSame($expected, $actual);
        $this->assertSame($lateExact->id, $actual[0]);
    }

    public function test_identifier_paths_preserve_zeroes_lots_and_branch_isolation(): void
    {
        $target = $this->product('Coca Cola 600ml', [], '0012345678901');
        $assignment = BranchProduct::where('product_id', $target->id)->firstOrFail();
        ProductBatch::create([
            'branch_product_id' => $assignment->id, 'lot_number' => 'LOT-AB924',
            'quantity' => 5, 'initial_quantity' => 5, 'status' => ProductBatch::STATUS_ACTIVE,
        ]);
        $otherBranch = Branch::create(['name' => 'Otra', 'slug' => 'otra-busqueda', 'active' => true]);
        $other = $this->product('Coca Cola 600ml ajeno');
        BranchProduct::where('product_id', $other->id)->update(['branch_id' => $otherBranch->id]);

        $service = app(ProductSearchService::class);
        foreach (['0012345678901', '78901', 'coc 600', 'coca AB924'] as $term) {
            $legacy = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: 10000);
            $optimized = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: 10000, inventorySqlPrefilter: true);
            $this->assertSame([$target->id], $service->ids($term, $optimized)->all(), $term);
            $this->assertSame($service->ids($term, $legacy)->all(), $service->ids($term, $optimized)->all(), $term);
        }
    }

    public function test_dictionary_aliases_keep_their_global_category_and_product_targets(): void
    {
        $target = $this->product('Coca-Cola 600ml', [], '0012345678901');
        $dictionary = new ProductSearchDictionary;
        $property = new \ReflectionProperty($dictionary, 'dictionary');
        $property->setValue($dictionary, [
            'global' => ['coca cola' => ['chispa']],
            'categories' => ['refrescos' => ['gaseosa']],
            'products' => ['0012345678901' => ['favorito']],
        ]);
        $this->app->instance(ProductSearchDictionary::class, $dictionary);

        foreach (['chispa', 'gaseosa', 'favorito'] as $term) {
            $search = app(DatabaseProductSearch::class);
            $this->assertSame([$target->id], $search->ids($term, $this->searchOptions(true))->all(), $term);
            $this->assertSame($search->ids($term, $this->searchOptions(false))->all(), $search->ids($term, $this->searchOptions(true))->all(), $term);
        }
    }

    public function test_mixed_reference_search_scores_only_the_joint_candidates_and_does_not_reload_lot_texts(): void
    {
        $target = $this->product('Articulo Muestra 20261029044246 3035 250 ml');
        $this->seedUnrelatedProducts(650, 'Articulo Muestra');
        $legacy = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: 10000);
        $optimized = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: 10000, inventorySqlPrefilter: true);
        $term = $target->name;
        $expected = app(ProductSearchService::class)->ids($term, $legacy)->all();
        $retrieved = 0;
        Product::retrieved(function () use (&$retrieved): void {
            $retrieved++;
        });

        try {
            $query = BranchProduct::where('branch_id', $this->branch->id);
            $ids = app(ProductSearchService::class)->constrainBranchProducts($query, $term, options: $optimized);
            $this->assertSame([$target->id], $expected);
            $this->assertSame($expected, $ids->all());
            $this->assertSame([$target->id], $query->pluck('product_id')->all());
            $this->assertSame(1, $retrieved);
        } finally {
            Event::forget('eloquent.retrieved: '.Product::class);
        }
    }

    public function test_identifier_intersections_keep_first_term_order_and_preintersection_limits(): void
    {
        $first = $this->product('Chocolates 250 ml');
        $second = $this->product('Chocolate 250 ml');
        foreach ([$first, $second] as $product) {
            ProductBatch::create([
                'branch_product_id' => BranchProduct::where('product_id', $product->id)->value('id'),
                'lot_number' => 'LOT-AB924', 'quantity' => 5, 'initial_quantity' => 5,
                'status' => ProductBatch::STATUS_ACTIVE,
            ]);
        }

        foreach ([10000, 1] as $limit) {
            foreach (['chocolate AB924 250', 'AB924 chocolate 250', '250 chocolate AB924'] as $term) {
                $legacy = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: $limit);
                $optimized = new ProductSearchOptions(branchIds: [$this->branch->id], includeLotNumbers: true, limit: $limit, inventorySqlPrefilter: true);
                $this->assertSame(
                    app(ProductSearchService::class)->ids($term, $legacy)->all(),
                    app(ProductSearchService::class)->ids($term, $optimized)->all(),
                    $term.' limit '.$limit,
                );
            }
        }
    }

    private function searchOptions(bool $optimized): ProductSearchOptions
    {
        return new ProductSearchOptions(branchIds: [$this->branch->id], limit: 10000, inventorySqlPrefilter: $optimized);
    }

    private function product(string $name, array $extra = [], ?string $barcode = null): Product
    {
        $product = Product::create(['name' => $name, 'category_id' => $this->category->id, 'active' => true, ...$extra]);
        BranchProduct::create(['product_id' => $product->id, 'branch_id' => $this->branch->id, 'status' => BranchProduct::STATUS_ACTIVE]);
        if ($barcode !== null) {
            Barcode::create(['product_id' => $product->id, 'code' => $barcode, 'active' => true]);
        }

        return $product;
    }

    private function seedUnrelatedProducts(int $count, string $prefix = 'Artículo común'): void
    {
        $category = Category::create(['name' => 'Diversos', 'active' => true]);
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['name' => $prefix.' '.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'category_id' => $category->id, 'active' => true, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('products')->insert($rows);
        $assignments = DB::table('products')->where('category_id', $category->id)->pluck('id')->map(fn ($id) => [
            'product_id' => $id, 'branch_id' => $this->branch->id, 'status' => BranchProduct::STATUS_ACTIVE,
        ])->all();
        DB::table('branch_products')->insert($assignments);
    }
}
