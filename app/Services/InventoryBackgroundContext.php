<?php

namespace App\Services;

use Closure;
use Illuminate\Http\Request;

/** Limits durable background indexing to inventory operations. */
class InventoryBackgroundContext
{
    private static int $depth = 0;

    public function active(): bool
    {
        return self::$depth > 0;
    }

    public function handlesRequest(?Request $request): bool
    {
        return $this->active() || ($request?->routeIs(
            'inventory.branches.products.*',
            'inventory.branch-inventory.*',
            'inventory.product-batches.*',
            'inventory.stock-movements.*',
        ) ?? false);
    }

    public function run(Closure $operation): mixed
    {
        self::$depth++;

        try {
            return $operation();
        } finally {
            self::$depth--;
        }
    }
}
