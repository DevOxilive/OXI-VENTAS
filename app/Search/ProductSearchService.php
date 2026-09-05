<?php

namespace App\Search;

use App\Models\Barcode;
use App\Models\BranchProduct;
use App\Models\Product;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Meilisearch\Endpoints\Indexes;
use Throwable;

class ProductSearchService
{
    public function __construct(
        private readonly DatabaseProductSearch $databaseSearch,
        private readonly ProductIdentifierSearch $identifierSearch,
    ) {}

    public function ids(string $search, ?ProductSearchOptions $options = null): Collection
    {
        $search = trim($search);
        $options ??= new ProductSearchOptions;

        if ($search === '') {
            return collect();
        }

        if (preg_match('/^\d{8,14}$/', $search) === 1) {
            $exactIds = $this->exactBarcodeIds($search, $options);

            if ($exactIds->isNotEmpty()) {
                return $exactIds;
            }
        }

        if (config('scout.driver') === 'meilisearch') {
            try {
                return $this->searchWithIdentifiers(
                    $search,
                    $options,
                    fn (string $value) => $this->meilisearchIds($value, $options),
                );
            } catch (Throwable $exception) {
                Log::warning('Meilisearch no respondio; se uso la busqueda MySQL de respaldo.', [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);

                if (! config('product_search.fallback_enabled', true)) {
                    throw $exception;
                }
            }
        }

        return $this->searchWithIdentifiers(
            $search,
            $options,
            fn (string $value) => $this->databaseSearch->ids($value, $options),
        );
    }

    public function constrain(
        $query,
        string $search,
        string $productIdColumn,
        ?ProductSearchOptions $options = null,
        bool $orderByRelevance = false,
        bool $resetExistingOrder = false,
    ): Collection {
        $this->assertColumn($productIdColumn);
        $ids = $this->ids($search, $options);

        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return $ids;
        }

        $query->whereIntegerInRaw($productIdColumn, $ids->all());

        if ($orderByRelevance) {
            if ($resetExistingOrder) {
                $query->reorder();
            }

            $cases = $ids
                ->values()
                ->map(fn (int $id, int $position) => "WHEN {$id} THEN {$position}")
                ->implode(' ');
            $query->orderByRaw("CASE {$productIdColumn} {$cases} ELSE {$ids->count()} END");
        }

        return $ids;
    }

    public function constrainBranchProducts(
        $query,
        string $search,
        string $productIdColumn = 'branch_products.product_id',
        string $lotRelation = 'batches',
        ?ProductSearchOptions $options = null,
        bool $orderByRelevance = false,
        bool $resetExistingOrder = false,
    ): Collection {
        $this->assertColumn($productIdColumn);
        $this->assertRelation($lotRelation);
        $options ??= new ProductSearchOptions;
        $ids = $this->ids($search, $options);

        if ($ids->isEmpty()) {
            $query->whereRaw('1 = 0');

            return $ids;
        }

        $query->whereIntegerInRaw($productIdColumn, $ids->all());

        if ($options->includeLotNumbers) {
            foreach ($this->identifierSearch->terms($search) as $term) {
                if (! $this->identifierSearch->supportsLotFragment($term)) {
                    continue;
                }

                $productIdsWithoutLots = $this->ids($term, $options->withoutLotNumbers());
                $lotPattern = $this->identifierSearch->lotContainsPattern($term);

                $query->where(function ($termQuery) use (
                    $productIdsWithoutLots,
                    $productIdColumn,
                    $lotRelation,
                    $lotPattern,
                    $options,
                ) {
                    if ($productIdsWithoutLots->isEmpty()) {
                        $termQuery->whereRaw('1 = 0');
                    } else {
                        $termQuery->whereIntegerInRaw($productIdColumn, $productIdsWithoutLots->all());
                    }

                    $termQuery->orWhereHas($lotRelation, function (Builder $lotQuery) use ($lotPattern, $options) {
                        $lotQuery
                            ->whereNotNull('lot_number')
                            ->where('lot_number', 'like', $lotPattern)
                            ->when(
                                $options->lotStatuses !== [],
                                fn (Builder $query) => $query->whereIn('status', $options->lotStatuses),
                            )
                            ->when(
                                $options->onlyLotsWithStock,
                                fn (Builder $query) => $query->where('quantity', '>', 0),
                            );
                    });
                });
            }
        }

        if ($orderByRelevance) {
            if ($resetExistingOrder) {
                $query->reorder();
            }

            $cases = $ids
                ->values()
                ->map(fn (int $id, int $position) => "WHEN {$id} THEN {$position}")
                ->implode(' ');
            $query->orderByRaw("CASE {$productIdColumn} {$cases} ELSE {$ids->count()} END");
        }

        return $ids;
    }

    public function sortByRelevance(Collection $items, Collection $ids, string $productIdKey = 'product_id'): Collection
    {
        $positions = $ids->values()->flip();

        return $items
            ->sortBy(fn ($item) => $positions->get((int) data_get($item, $productIdKey), PHP_INT_MAX))
            ->values();
    }

    private function meilisearchIds(string $search, ProductSearchOptions $options): Collection
    {
        $builder = Product::search(
            $search,
            function (Indexes $index, string $query, array $searchOptions) {
                return $index->rawSearch($query, array_merge($searchOptions, [
                    'matchingStrategy' => 'all',
                    'attributesToRetrieve' => ['id'],
                ]));
            },
        );
        $branchIds = $options->normalizedBranchIds();

        if ($options->onlyActiveProducts) {
            $builder->where('active', true);
        }

        if ($branchIds !== []) {
            $builder->whereIn(
                $options->onlyActiveBranchProducts ? 'active_branch_ids' : 'branch_ids',
                $branchIds,
            );
        } elseif ($options->onlyActiveBranchProducts) {
            $builder->where('has_active_branch', true);
        }

        return $builder
            ->take(min($options->limit, (int) config('product_search.max_results', 10000)))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function searchWithIdentifiers(
        string $search,
        ProductSearchOptions $options,
        Closure $searchEngine,
    ): Collection {
        $terms = $this->identifierSearch->terms($search);

        if (! $this->identifierSearch->hasRelevantTerm($terms, $options)) {
            return $searchEngine($search)
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        $matchesByTerm = [];
        $preferredOrder = collect();

        foreach ($terms as $term) {
            $identifierMatches = $this->identifierSearch->idsForTerm($term, $options);
            $termMatches = ($identifierMatches->isNotEmpty()
                ? $identifierMatches
                : $searchEngine($term))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($termMatches->isEmpty()) {
                return collect();
            }

            $matchesByTerm[] = $termMatches;
            $preferredOrder = $preferredOrder
                ->merge($termMatches);
        }

        $candidateIds = array_shift($matchesByTerm) ?? collect();

        foreach ($matchesByTerm as $termMatches) {
            $candidateIds = $candidateIds->intersect($termMatches)->values();
        }

        if ($candidateIds->isEmpty()) {
            return collect();
        }

        $candidateSet = $candidateIds->flip();

        return $preferredOrder
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $candidateSet->has($id))
            ->unique()
            ->take(min($options->limit, (int) config('product_search.max_results', 10000)))
            ->values();
    }

    private function exactBarcodeIds(string $barcode, ProductSearchOptions $options): Collection
    {
        $branchIds = $options->normalizedBranchIds();
        $productIds = Barcode::query()
            ->where('active', true)
            ->where('code', $barcode)
            ->pluck('product_id')
            ->merge(
                BranchProduct::query()
                    ->where('barcode', $barcode)
                    ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                    ->when($options->onlyActiveBranchProducts, fn ($query) => $query->where('status', BranchProduct::STATUS_ACTIVE))
                    ->pluck('product_id'),
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->whereIntegerInRaw('id', $productIds->all())
            ->when($options->onlyActiveProducts, fn (Builder $query) => $query->where('active', true))
            ->when($branchIds !== [] || $options->onlyActiveBranchProducts, function (Builder $query) use ($branchIds, $options) {
                $query->whereHas('branchProducts', function (Builder $branchQuery) use ($branchIds, $options) {
                    $branchQuery
                        ->when($branchIds !== [], fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                        ->when($options->onlyActiveBranchProducts, fn (Builder $query) => $query->where('status', BranchProduct::STATUS_ACTIVE));
                });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function assertColumn(string $column): void
    {
        if (preg_match('/^[a-z_][a-z0-9_.]*$/i', $column) !== 1) {
            throw new \InvalidArgumentException('La columna usada para filtrar productos no es valida.');
        }
    }

    private function assertRelation(string $relation): void
    {
        if (preg_match('/^[a-z_][a-z0-9_.]*$/i', $relation) !== 1) {
            throw new \InvalidArgumentException('La relacion usada para filtrar lotes no es valida.');
        }
    }
}
