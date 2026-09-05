<?php

namespace App\Observers;

use App\Models\Barcode;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class ProductSearchRelationObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $relation): void
    {
        if (! $relation->wasRecentlyCreated && ! $relation->wasChanged($this->searchableColumns($relation))) {
            return;
        }

        $this->reindexRelatedProducts($relation);
    }

    public function deleted(Model $relation): void
    {
        $this->reindexRelatedProducts($relation);
    }

    public function restored(Model $relation): void
    {
        $this->reindexRelatedProducts($relation);
    }

    public function forceDeleted(Model $relation): void
    {
        $this->reindexRelatedProducts($relation);
    }

    private function searchableColumns(Model $relation): array
    {
        return match ($relation::class) {
            Barcode::class => ['product_id', 'code', 'active', 'deleted_at'],
            BranchProduct::class => ['product_id', 'branch_id', 'barcode', 'status', 'deleted_at'],
            default => [],
        };
    }

    private function reindexRelatedProducts(Model $relation): void
    {
        $productIds = collect([
            $relation->getAttribute('product_id'),
            $relation->getOriginal('product_id'),
        ])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($productIds !== []) {
            Product::query()->whereIntegerInRaw('id', $productIds)->searchable();
        }
    }
}
