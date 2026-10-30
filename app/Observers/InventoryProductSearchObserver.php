<?php

namespace App\Observers;

use App\Services\InventoryBackgroundContext;
use App\Services\InventoryOutbox;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\ModelObserver;

class InventoryProductSearchObserver extends ModelObserver
{
    private bool $deferOutsideInventory;

    public function __construct()
    {
        parent::__construct();
        $this->deferOutsideInventory = (bool) $this->afterCommit;
        // Inventory must record its pending work before the business transaction commits.
        $this->afterCommit = false;
    }

    public function saved($model)
    {
        $this->handle($model, fn () => parent::saved($model));
    }

    public function deleted($model)
    {
        $this->handle($model, fn () => parent::deleted($model));
    }

    public function restored($model)
    {
        $this->handle($model, fn () => parent::restored($model));
    }

    public function forceDeleted($model)
    {
        $this->handle($model, fn () => parent::forceDeleted($model));
    }

    private function handle($model, Closure $original): void
    {
        if (static::syncingDisabledFor($model)) {
            return;
        }

        if (app(InventoryBackgroundContext::class)->active()) {
            app(InventoryOutbox::class)->searchProduct((int) $model->getKey());

            return;
        }

        if ($this->deferOutsideInventory) {
            DB::afterCommit($original);
        } else {
            $original();
        }
    }
}
