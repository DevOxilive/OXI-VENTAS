<?php

namespace Tests\Unit;

use App\Search\ProductIdentifierSearch;
use Tests\TestCase;

class ProductIdentifierSearchTest extends TestCase
{
    public function test_it_recognizes_safe_barcode_suffixes(): void
    {
        config()->set('product_search.minimum_barcode_suffix_length', 5);
        $search = new ProductIdentifierSearch;

        $this->assertTrue($search->supportsBarcodeSuffix('67890'));
        $this->assertFalse($search->supportsBarcodeSuffix('7890'));
        $this->assertFalse($search->supportsBarcodeSuffix('CK924'));
    }

    public function test_it_recognizes_alphanumeric_lot_fragments_without_confusing_words(): void
    {
        config()->set('product_search.minimum_lot_fragment_length', 4);
        $search = new ProductIdentifierSearch;

        $this->assertTrue($search->supportsLotFragment('ck924'));
        $this->assertTrue($search->supportsLotFragment('009-1'));
        $this->assertFalse($search->supportsLotFragment('coca'));
        $this->assertFalse($search->supportsLotFragment('a12'));
    }

    public function test_it_preserves_lot_format_while_splitting_combined_queries(): void
    {
        $search = new ProductIdentifierSearch;

        $this->assertSame(['coca', 'ck924'], $search->terms('  Coca   CK924  '));
        $this->assertSame(['009-1'], $search->terms('(009-1)'));
    }
}
