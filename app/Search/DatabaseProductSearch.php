<?php

namespace App\Search;

use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DatabaseProductSearch
{
    public function __construct(
        private readonly ProductSearchDocumentBuilder $documents,
    ) {}

    public function ids(string $search, ProductSearchOptions $options): Collection
    {
        $queryTerms = $this->documents->tokens($search);

        if ($queryTerms === []) {
            return collect();
        }

        if ($options->inventorySqlPrefilter) {
            $prefilter = app(InventoryProductSqlPrefilter::class);
            if ($prefilter->supportsConnection((new Product)->getConnection())) {
                return $this->inventoryIds($queryTerms, $options, $prefilter);
            }
        }

        $branchIds = $options->normalizedBranchIds();
        $products = Product::query()
            ->with(['category.productDepartment', 'barcodes', 'branchProducts'])
            ->when($options->onlyActiveProducts, fn ($query) => $query->where('active', true))
            ->when($branchIds !== [] || $options->onlyActiveBranchProducts, function ($query) use ($branchIds, $options) {
                $query->whereHas('branchProducts', function ($branchQuery) use ($branchIds, $options) {
                    $branchQuery
                        ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                        ->when($options->onlyActiveBranchProducts, fn ($query) => $query->where('status', BranchProduct::STATUS_ACTIVE));
                });
            })
            ->get();

        return $products
            ->map(function (Product $product) use ($queryTerms) {
                $document = $this->documents->build($product);
                $score = $this->score($queryTerms, $document);

                return $score === null ? null : [
                    'id' => (int) $product->getKey(),
                    'name' => (string) $product->name,
                    'score' => $score,
                ];
            })
            ->filter()
            ->sortBy([
                ['score', 'asc'],
                ['name', 'asc'],
            ])
            ->take($options->limit)
            ->pluck('id')
            ->values();
    }

    private function inventoryIds(array $queryTerms, ProductSearchOptions $options, InventoryProductSqlPrefilter $prefilter): Collection
    {
        $query = $this->inventoryQuery($options);
        $prefilter->apply($query, $queryTerms);

        return $this->rankInventoryCandidates($query, [$queryTerms], $options);
    }

    /** Null conserva el camino anterior cuando sus límites podrían cambiar la intersección. */
    public function intersectInventoryTerms(array $searches, ProductSearchOptions $options, ?Collection $candidateIds, ?Collection $preferredOrder): ?Collection
    {
        $prefilter = app(InventoryProductSqlPrefilter::class);
        $query = $this->inventoryQuery($options);
        if (! $prefilter->supportsConnection($query->getConnection())
            || (clone $query)->withoutEagerLoads()->count() > $options->limit) {
            return null;
        }

        if ($candidateIds !== null) {
            $query->whereIntegerInRaw('products.id', $candidateIds->all());
        }
        $queryGroups = array_map(fn (string $search) => $this->documents->tokens($search), $searches);
        foreach ($queryGroups as $terms) {
            if ($terms === []) {
                return collect();
            }
        }
        $prefilter->apply($query, array_values(array_unique(array_merge(...$queryGroups))));

        return $this->rankInventoryCandidates($query, $queryGroups, $options, $preferredOrder);
    }

    private function inventoryQuery(ProductSearchOptions $options): Builder
    {
        $branchIds = $options->normalizedBranchIds();

        return Product::query()
            ->select(['id', 'name', 'search_aliases', 'category_id', 'active', 'inventory_quantity_mode'])
            ->with([
                'category:id,name,product_department_id',
                'category.productDepartment:id,name',
                'barcodes:id,product_id,code,active',
                'branchProducts:id,product_id,branch_id,barcode,status',
            ])
            ->when($options->onlyActiveProducts, fn ($query) => $query->where('active', true))
            ->when($branchIds !== [] || $options->onlyActiveBranchProducts, function ($query) use ($branchIds, $options) {
                $query->whereHas('branchProducts', function ($assignments) use ($branchIds, $options) {
                    $assignments->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                        ->when($options->onlyActiveBranchProducts, fn ($query) => $query->where('status', BranchProduct::STATUS_ACTIVE));
                });
            });

    }

    private function rankInventoryCandidates(Builder $query, array $queryGroups, ProductSearchOptions $options, ?Collection $preferredOrder = null): Collection
    {
        $ranked = collect();
        $positions = $preferredOrder?->flip();
        $query->chunkById(250, function (Collection $products) use ($queryGroups, $positions, $options, &$ranked): void {
            $matches = $products->map(function (Product $product) use ($queryGroups, $positions) {
                $document = $this->documents->build($product);
                $scores = [];
                foreach ($queryGroups as $terms) {
                    $score = $this->score($terms, $document);
                    if ($score === null) {
                        return null;
                    }
                    $scores[] = $score;
                }

                return ['id' => (int) $product->id, 'name' => (string) $product->name, 'score' => $positions?->get((int) $product->id, PHP_INT_MAX) ?? $scores[0]];
            })->filter();

            // Todos los candidatos se puntúan; solo los mejores resultados se conservan en memoria.
            $ranked = $ranked->concat($matches)->sortBy([
                ['score', 'asc'], ['name', 'asc'], ['id', 'asc'],
            ])->take($options->limit)->values();
        });

        return $ranked->pluck('id')->values();
    }

    private function score(array $queryTerms, array $document): ?float
    {
        $fields = [
            ['values' => $document['barcodes'], 'weight' => 0, 'codes' => true],
            ['values' => $document['barcode_suffixes'], 'weight' => 1, 'codes' => true],
            ['values' => $document['branch_barcodes'], 'weight' => 0, 'codes' => true],
            ['values' => $this->documents->tokens($document['name']), 'weight' => 0, 'codes' => false],
            ['values' => $document['aliases'], 'weight' => 2, 'codes' => false],
            ['values' => $document['search_terms'], 'weight' => 4, 'codes' => false],
        ];
        $score = 0.0;

        foreach ($queryTerms as $queryTerm) {
            $best = null;

            foreach ($fields as $field) {
                foreach ($field['values'] as $candidate) {
                    $candidateScore = $this->termScore(
                        $queryTerm,
                        $this->documents->normalize((string) $candidate),
                        $field['codes'],
                    );

                    if ($candidateScore !== null) {
                        $best = min($best ?? INF, $candidateScore + $field['weight']);
                    }
                }
            }

            if ($best === null) {
                return null;
            }

            $score += $best;
        }

        return $score;
    }

    private function termScore(string $query, string $candidate, bool $code): ?float
    {
        if ($query === $candidate) {
            return 0;
        }

        if ($candidate === '') {
            return null;
        }

        $numeric = ctype_digit($query);

        if (! $numeric && str_starts_with($candidate, $query)) {
            return 1 + ((mb_strlen($candidate) - mb_strlen($query)) / 100);
        }

        if ($numeric || $code) {
            return null;
        }

        $allowedTypos = match (true) {
            mb_strlen($query) >= 9 => 2,
            mb_strlen($query) >= 5 => 1,
            default => 0,
        };

        if ($allowedTypos === 0 || abs(mb_strlen($candidate) - mb_strlen($query)) > $allowedTypos) {
            return null;
        }

        $distance = levenshtein($query, $candidate);

        if (mb_substr($query, 0, 1) !== mb_substr($candidate, 0, 1)) {
            $distance++;
        }

        return $distance <= $allowedTypos ? 10 + $distance : null;
    }
}
