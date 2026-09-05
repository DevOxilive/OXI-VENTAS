<?php

namespace App\Search;

use App\Models\ProductBatch;

final readonly class ProductSearchOptions
{
    /**
     * @param  array<int, int|string>  $branchIds
     * @param  array<int, string>  $lotStatuses
     */
    public function __construct(
        public array $branchIds = [],
        public bool $onlyActiveProducts = false,
        public bool $onlyActiveBranchProducts = false,
        public int $limit = 1000,
        public bool $includeLotNumbers = false,
        public array $lotStatuses = [ProductBatch::STATUS_ACTIVE],
        public bool $onlyLotsWithStock = true,
    ) {}

    /**
     * @return array<int, int>
     */
    public function normalizedBranchIds(): array
    {
        return collect($this->branchIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function withoutLotNumbers(): self
    {
        return new self(
            branchIds: $this->branchIds,
            onlyActiveProducts: $this->onlyActiveProducts,
            onlyActiveBranchProducts: $this->onlyActiveBranchProducts,
            limit: $this->limit,
            includeLotNumbers: false,
            lotStatuses: $this->lotStatuses,
            onlyLotsWithStock: $this->onlyLotsWithStock,
        );
    }
}
