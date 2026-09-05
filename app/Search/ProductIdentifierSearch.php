<?php

namespace App\Search;

use App\Models\Barcode;
use App\Models\BranchProduct;
use App\Models\Product;
use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductIdentifierSearch
{
    public function terms(string $search): array
    {
        return collect(preg_split('/\s+/u', mb_strtolower(trim($search))) ?: [])
            ->map(fn (string $term) => trim($term, " \t\n\r\0\x0B,;:()[]{}"))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function hasRelevantTerm(array $terms, ProductSearchOptions $options): bool
    {
        return collect($terms)->contains(
            fn (string $term) => $this->supportsBarcodeSuffix($term)
                || ($options->includeLotNumbers && $this->supportsLotFragment($term)),
        );
    }

    public function idsForTerm(string $term, ProductSearchOptions $options): Collection
    {
        $ids = collect();

        if ($this->supportsBarcodeSuffix($term)) {
            $ids = $ids->merge($this->barcodeSuffixIds($term, $options));
        }

        if ($options->includeLotNumbers && $this->supportsLotFragment($term)) {
            $ids = $ids->merge($this->lotNumberIds($term, $options));
        }

        return $ids
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->take($options->limit)
            ->values();
    }

    public function supportsBarcodeSuffix(string $term): bool
    {
        return ctype_digit($term)
            && mb_strlen($term) >= (int) config('product_search.minimum_barcode_suffix_length', 5);
    }

    public function supportsLotFragment(string $term): bool
    {
        $plainLength = mb_strlen(preg_replace('/[^a-z0-9]/i', '', $term) ?? '');

        return $plainLength >= (int) config('product_search.minimum_lot_fragment_length', 4)
            && preg_match('/\d/u', $term) === 1
            && preg_match('/^[\p{L}\p{N}._\/-]+$/u', $term) === 1;
    }

    public function lotContainsPattern(string $fragment): string
    {
        return '%'.$this->escapeLike($fragment).'%';
    }

    private function barcodeSuffixIds(string $suffix, ProductSearchOptions $options): Collection
    {
        $branchIds = $options->normalizedBranchIds();
        $pattern = "%{$suffix}";

        $globalIds = Barcode::query()
            ->where('active', true)
            ->where('code', $suffix)
            ->pluck('product_id')
            ->merge(
                Barcode::query()
                    ->where('active', true)
                    ->where('code', 'like', $pattern)
                    ->where('code', '!=', $suffix)
                    ->pluck('product_id'),
            );

        $branchIdsByCode = BranchProduct::query()
            ->where('barcode', $suffix)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($options->onlyActiveBranchProducts, fn ($query) => $query->where('status', BranchProduct::STATUS_ACTIVE))
            ->pluck('product_id')
            ->merge(
                BranchProduct::query()
                    ->where('barcode', 'like', $pattern)
                    ->where('barcode', '!=', $suffix)
                    ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                    ->when($options->onlyActiveBranchProducts, fn ($query) => $query->where('status', BranchProduct::STATUS_ACTIVE))
                    ->pluck('product_id'),
            );

        return $this->filterProductIds(
            $globalIds->merge($branchIdsByCode),
            $options,
        );
    }

    private function lotNumberIds(string $fragment, ProductSearchOptions $options): Collection
    {
        $query = ProductBatch::query()
            ->join('branch_products', 'branch_products.id', '=', 'product_batches.branch_product_id')
            ->whereNotNull('product_batches.lot_number')
            ->when(
                $options->normalizedBranchIds() !== [],
                fn ($query) => $query->whereIn('branch_products.branch_id', $options->normalizedBranchIds()),
            )
            ->when(
                $options->onlyActiveBranchProducts,
                fn ($query) => $query->where('branch_products.status', BranchProduct::STATUS_ACTIVE),
            )
            ->when(
                $options->lotStatuses !== [],
                fn ($query) => $query->whereIn('product_batches.status', $options->lotStatuses),
            )
            ->when(
                $options->onlyLotsWithStock,
                fn ($query) => $query->where('product_batches.quantity', '>', 0),
            );

        $exactIds = (clone $query)
            ->where('product_batches.lot_number', $fragment)
            ->pluck('branch_products.product_id');
        $partialIds = (clone $query)
            ->where('product_batches.lot_number', 'like', $this->lotContainsPattern($fragment))
            ->where('product_batches.lot_number', '!=', $fragment)
            ->pluck('branch_products.product_id');

        return $this->filterProductIds(
            $exactIds->merge($partialIds),
            $options,
        );
    }

    private function filterProductIds(Collection $productIds, ProductSearchOptions $options): Collection
    {
        $productIds = $productIds
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        $positions = $productIds->flip();
        $branchIds = $options->normalizedBranchIds();

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
            ->sortBy(fn (int $id) => $positions->get($id, PHP_INT_MAX))
            ->take($options->limit)
            ->values();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
