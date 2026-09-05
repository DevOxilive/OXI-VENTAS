<?php

namespace Tests\Unit;

use App\Search\DatabaseProductSearch;
use App\Search\ProductIdentifierSearch;
use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use Mockery;
use Tests\TestCase;

class ProductSearchServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_resolves_a_barcode_suffix_through_the_parent_search_service(): void
    {
        config()->set('scout.driver', 'database');
        $options = new ProductSearchOptions;
        $database = Mockery::mock(DatabaseProductSearch::class);
        $identifiers = Mockery::mock(ProductIdentifierSearch::class)->makePartial();

        $database->shouldNotReceive('ids');
        $identifiers->shouldReceive('idsForTerm')
            ->once()
            ->with('67890', $options)
            ->andReturn(collect([42]));

        $results = (new ProductSearchService($database, $identifiers))->ids('67890', $options);

        $this->assertSame([42], $results->all());
    }

    public function test_it_combines_a_product_word_with_a_lot_fragment(): void
    {
        config()->set('scout.driver', 'database');
        $options = new ProductSearchOptions(includeLotNumbers: true);
        $database = Mockery::mock(DatabaseProductSearch::class);
        $identifiers = Mockery::mock(ProductIdentifierSearch::class)->makePartial();

        $database->shouldReceive('ids')->once()->with('coca', $options)->andReturn(collect([20, 30]));
        $identifiers->shouldReceive('idsForTerm')->once()->with('coca', $options)->andReturn(collect());
        $identifiers->shouldReceive('idsForTerm')->once()->with('ck924', $options)->andReturn(collect([30, 40]));

        $results = (new ProductSearchService($database, $identifiers))->ids('coca CK924', $options);

        $this->assertSame([30], $results->all());
    }

    public function test_it_keeps_the_fast_path_for_searches_without_identifiers(): void
    {
        config()->set('scout.driver', 'database');
        $options = new ProductSearchOptions;
        $database = Mockery::mock(DatabaseProductSearch::class);
        $identifiers = Mockery::mock(ProductIdentifierSearch::class)->makePartial();

        $database->shouldReceive('ids')
            ->once()
            ->with('submari', $options)
            ->andReturn(collect([7, 8]));
        $identifiers->shouldNotReceive('idsForTerm');

        $results = (new ProductSearchService($database, $identifiers))->ids('submari', $options);

        $this->assertSame([7, 8], $results->all());
    }

    public function test_a_real_identifier_match_takes_priority_over_textual_noise(): void
    {
        config()->set('scout.driver', 'database');
        $options = new ProductSearchOptions(includeLotNumbers: true);
        $database = Mockery::mock(DatabaseProductSearch::class);
        $identifiers = Mockery::mock(ProductIdentifierSearch::class)->makePartial();

        $database->shouldNotReceive('ids');
        $identifiers->shouldReceive('idsForTerm')
            ->once()
            ->with('009-1', $options)
            ->andReturn(collect([1, 3]));

        $results = (new ProductSearchService($database, $identifiers))->ids('009-1', $options);

        $this->assertSame([1, 3], $results->all());
    }
}
