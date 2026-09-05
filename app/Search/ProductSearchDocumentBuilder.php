<?php

namespace App\Search;

use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductSearchDocumentBuilder
{
    public function __construct(
        private readonly ProductSearchDictionary $dictionary,
    ) {}

    public function build(Product $product): array
    {
        $product->loadMissing([
            'category.productDepartment',
            'barcodes',
            'branchProducts',
        ]);

        $barcodes = $product->barcodes
            ->where('active', true)
            ->pluck('code')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $branchProducts = $product->branchProducts;
        $branchBarcodes = $branchProducts
            ->pluck('barcode')
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $category = trim((string) ($product->category?->name ?? ''));
        $department = trim((string) ($product->category?->productDepartment?->name ?? ''));
        $sourceTerms = $this->tokens(implode(' ', [
            $product->name,
            $category,
            $department,
        ]));
        $manualAliases = collect($product->search_aliases ?? [])
            ->filter(fn ($alias) => is_string($alias))
            ->map(fn (string $alias) => $this->normalize($alias))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $dictionaryAliases = $this->dictionary->aliasesFor(
            (int) $product->getKey(),
            $category,
            array_values(array_unique([...$barcodes, ...$branchBarcodes])),
            $sourceTerms,
        );
        $aliases = array_values(array_unique([
            ...$manualAliases,
            ...$dictionaryAliases,
        ]));
        $searchTerms = array_values(array_unique([
            ...$sourceTerms,
            ...$this->tokens(implode(' ', $aliases)),
        ]));
        $activeBranchIds = $branchProducts
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->pluck('branch_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return [
            'id' => (int) $product->getKey(),
            'name' => (string) $product->name,
            'barcodes' => $barcodes,
            'barcode_suffixes' => $this->barcodeSuffixes($barcodes),
            'branch_barcodes' => $branchBarcodes,
            'search_terms' => $searchTerms,
            'prefixes' => $this->prefixes($searchTerms),
            'aliases' => $aliases,
            'category' => $category,
            'department' => $department,
            'category_id' => (int) ($product->category_id ?? 0),
            'department_id' => (int) ($product->category?->product_department_id ?? 0),
            'active' => (bool) $product->active,
            'branch_ids' => $branchProducts
                ->pluck('branch_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'active_branch_ids' => $activeBranchIds,
            'has_active_branch' => $activeBranchIds !== [],
            'inventory_quantity_mode' => (string) $product->inventory_quantity_mode,
        ];
    }

    public function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = preg_replace('/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    public function tokens(string $value): array
    {
        $normalized = $this->normalize($value);

        if ($normalized === '') {
            return [];
        }

        return collect(preg_split('/\s+/u', $normalized) ?: [])
            ->filter(fn (string $term) => $term !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function prefixes(array $terms): array
    {
        $minimumLength = max(2, (int) config('product_search.minimum_prefix_length', 3));
        $prefixes = [];

        foreach ($terms as $term) {
            if (preg_match('/^[a-z]+$/', $term) !== 1) {
                continue;
            }

            $length = mb_strlen($term);

            for ($size = $minimumLength; $size < $length; $size++) {
                $prefixes[] = mb_substr($term, 0, $size);
            }
        }

        return array_values(array_unique($prefixes));
    }

    private function barcodeSuffixes(array $barcodes): array
    {
        $minimumLength = max(4, (int) config('product_search.minimum_barcode_suffix_length', 5));
        $suffixes = [];

        foreach ($barcodes as $barcode) {
            $barcode = trim((string) $barcode);

            if (! ctype_digit($barcode)) {
                continue;
            }

            for ($size = $minimumLength; $size < strlen($barcode); $size++) {
                $suffixes[] = substr($barcode, -$size);
            }
        }

        return array_values(array_unique($suffixes));
    }
}
