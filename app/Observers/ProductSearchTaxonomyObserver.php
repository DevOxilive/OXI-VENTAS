<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDepartment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

class ProductSearchTaxonomyObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $taxonomy): void
    {
        if (! $taxonomy->wasRecentlyCreated && ! $taxonomy->wasChanged(['name', 'product_department_id'])) {
            return;
        }

        $this->reindexProducts($taxonomy);
    }

    public function deleted(Model $taxonomy): void
    {
        $this->reindexProducts($taxonomy);
    }

    public function restored(Model $taxonomy): void
    {
        $this->reindexProducts($taxonomy);
    }

    private function reindexProducts(Model $taxonomy): void
    {
        $query = Product::query();

        if ($taxonomy instanceof Category) {
            $query->where('category_id', $taxonomy->getKey());
        } elseif ($taxonomy instanceof ProductDepartment) {
            $query->whereHas('category', fn ($categoryQuery) => $categoryQuery
                ->where('product_department_id', $taxonomy->getKey()));
        } else {
            return;
        }

        $query->searchable();
    }
}
