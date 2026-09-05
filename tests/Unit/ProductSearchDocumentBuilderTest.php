<?php

namespace Tests\Unit;

use App\Models\Barcode;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDepartment;
use App\Search\ProductSearchDictionary;
use App\Search\ProductSearchDocumentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class ProductSearchDocumentBuilderTest extends TestCase
{
    public function test_it_builds_search_terms_and_prefixes_from_the_product_catalog(): void
    {
        $product = $this->product('desechable / Reyma / mariel charola termica 855 c / 50 pz');

        $document = $this->builder()->build($product);

        $this->assertContains('charola', $document['search_terms']);
        $this->assertContains('855', $document['search_terms']);
        $this->assertContains('cha', $document['prefixes']);
        $this->assertContains('charol', $document['prefixes']);
        $this->assertNotContains('85', $document['prefixes']);
    }

    public function test_it_separates_measurements_and_exposes_branch_filters(): void
    {
        $product = $this->product('Coca Cola 600ml');

        $document = $this->builder()->build($product);

        $this->assertContains('600', $document['search_terms']);
        $this->assertContains('ml', $document['search_terms']);
        $this->assertSame([1], $document['branch_ids']);
        $this->assertSame([1], $document['active_branch_ids']);
        $this->assertTrue($document['has_active_branch']);
        $this->assertSame(['7501000000001'], $document['barcodes']);
        $this->assertContains('00001', $document['barcode_suffixes']);
        $this->assertContains('01000000001', $document['barcode_suffixes']);
        $this->assertNotContains('0001', $document['barcode_suffixes']);
        $this->assertSame(['7501000000002'], $document['branch_barcodes']);
    }

    public function test_it_includes_manual_product_aliases_and_their_prefixes(): void
    {
        $product = $this->product('Skwinkles rellenos sabor sandia');
        $product->search_aliases = ['SKW', 'dulce sandía'];

        $document = $this->builder()->build($product);

        $this->assertContains('skw', $document['aliases']);
        $this->assertContains('dulce sandia', $document['aliases']);
        $this->assertContains('skw', $document['search_terms']);
        $this->assertContains('dul', $document['prefixes']);
    }

    private function builder(): ProductSearchDocumentBuilder
    {
        return new ProductSearchDocumentBuilder(new ProductSearchDictionary);
    }

    private function product(string $name): Product
    {
        $department = new ProductDepartment(['name' => 'Dulceria']);
        $department->id = 4;

        $category = new Category([
            'name' => 'Galletas y pastelillos',
            'product_department_id' => 4,
        ]);
        $category->id = 8;
        $category->setRelation('productDepartment', $department);

        $barcode = new Barcode([
            'code' => '7501000000001',
            'active' => true,
        ]);

        $branchProduct = new BranchProduct([
            'branch_id' => 1,
            'barcode' => '7501000000002',
            'status' => BranchProduct::STATUS_ACTIVE,
        ]);

        $product = new Product([
            'name' => $name,
            'category_id' => 8,
            'active' => true,
            'inventory_quantity_mode' => 'base_unit',
        ]);
        $product->id = 99;
        $product->setRelation('category', $category);
        $product->setRelation('barcodes', new Collection([$barcode]));
        $product->setRelation('branchProducts', new Collection([$branchProduct]));

        return $product;
    }
}
