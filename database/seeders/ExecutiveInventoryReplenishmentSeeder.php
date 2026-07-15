<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchProduct;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class ExecutiveInventoryReplenishmentSeeder extends Seeder
{
    public const MOVEMENT_NOTE_PREFIX = 'Seeder dashboard CEO:';

    private const HISTORY_DAYS = 365;

    public function run(): void
    {
        Event::fakeFor(function () {
            DB::transaction(function () {
                $this->clearExecutiveInventoryMovements();

                $stockService = app(StockMovementService::class);
                $branches = Branch::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get();

                $branches->each(function (Branch $branch, int $branchIndex) use ($stockService) {
                    $this->prepareBaseStock(
                        branch: $branch,
                        branchIndex: $branchIndex,
                        stockService: $stockService,
                    );

                    foreach (range(self::HISTORY_DAYS - 1, 0, 7) as $daysAgo) {
                        $this->replenishWeek(
                            branch: $branch,
                            branchIndex: $branchIndex,
                            stockService: $stockService,
                            saleDate: now()->subDays($daysAgo)->startOfDay(),
                        );
                    }

                    $this->syncBranchInventory($branch);
                });
            });
        });
    }

    private function prepareBaseStock(
        Branch $branch,
        int $branchIndex,
        StockMovementService $stockService,
    ): void {
        $restockDate = now()->subDays(self::HISTORY_DAYS + 5)->setTime(8, 30);

        BranchProduct::query()
            ->with('product.category')
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query->where('active', true))
            ->orderBy('id')
            ->take(14)
            ->get()
            ->each(function (BranchProduct $branchProduct, int $index) use (
                $branch,
                $branchIndex,
                $restockDate,
                $stockService,
            ) {
                $targetStock = $this->baseTargetStock($branchProduct, $branchIndex, $index);
                $currentStock = (float) $branchProduct->stock;

                if ($currentStock >= $targetStock) {
                    return;
                }

                $replenishment = round($targetStock - $currentStock, 2);

                if ($replenishment <= 0) {
                    return;
                }

                $receivedAt = $restockDate->copy()->addDays($index % 4);
                $movement = $stockService->move(
                    branchProduct: $branchProduct->fresh(),
                    type: StockMovement::TYPE_IN,
                    reason: StockMovement::REASON_PURCHASE,
                    quantity: $replenishment,
                    notes: self::MOVEMENT_NOTE_PREFIX . ' abastecimiento base para simulacion comercial.',
                    userId: null,
                    batches: [[
                        'lot_number' => strtoupper($branch->slug) . '-CEO-BASE-' . str_pad((string) $branchProduct->id, 4, '0', STR_PAD_LEFT),
                        'expiration_date' => now()->addMonths(4 + (($branchIndex + $index) % 5))->toDateString(),
                        'received_at' => $receivedAt->toDateString(),
                        'quantity' => $replenishment,
                        'supplier' => 'Proveedor base dashboard',
                    ]],
                );

                $this->dateStockMovement($movement, $receivedAt);
            });
    }

    private function replenishWeek(
        Branch $branch,
        int $branchIndex,
        StockMovementService $stockService,
        $saleDate,
    ): void {
        BranchProduct::query()
            ->with('product.category')
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query->where('active', true))
            ->orderBy('id')
            ->take(16)
            ->get()
            ->each(function (BranchProduct $branchProduct, int $index) use (
                $branch,
                $branchIndex,
                $saleDate,
                $stockService,
            ) {
                $targetStock = $this->monthlyTargetStock($branchProduct, $branchIndex, $index);
                $currentStock = (float) $branchProduct->stock;

                if ($currentStock >= $targetStock) {
                    return;
                }

                $replenishment = round($targetStock - $currentStock, 2);
                $receivedAt = $saleDate->copy()->setTime(7 + ($index % 2), 20);

                $movement = $stockService->move(
                    branchProduct: $branchProduct->fresh(),
                    type: StockMovement::TYPE_IN,
                    reason: StockMovement::REASON_PURCHASE,
                    quantity: $replenishment,
                    notes: self::MOVEMENT_NOTE_PREFIX . ' reabastecimiento semanal por demanda historica diaria.',
                    userId: null,
                    batches: [[
                        'lot_number' => strtoupper($branch->slug) . '-CEO-' . $saleDate->format('Ymd') . '-' . str_pad((string) $branchProduct->id, 4, '0', STR_PAD_LEFT),
                        'expiration_date' => $saleDate->copy()->addMonths(5 + (($branchIndex + $index) % 7))->toDateString(),
                        'received_at' => $receivedAt->toDateString(),
                        'quantity' => $replenishment,
                        'supplier' => 'Proveedor recurrente dashboard',
                    ]],
                );

                $this->dateStockMovement($movement, $receivedAt);
            });
    }

    private function clearExecutiveInventoryMovements(): void
    {
        $movementIds = StockMovement::query()
            ->where('notes', 'like', self::MOVEMENT_NOTE_PREFIX . '%')
            ->pluck('id');

        if ($movementIds->isNotEmpty()) {
            StockMovementBatch::whereIn('stock_movement_id', $movementIds)->delete();
            StockMovement::whereIn('id', $movementIds)->delete();
        }

        ProductBatch::query()
            ->where('lot_number', 'like', '%-CEO-%')
            ->delete();

        BranchProduct::query()
            ->each(function (BranchProduct $branchProduct) {
                $stock = ProductBatch::query()
                    ->where('branch_product_id', $branchProduct->id)
                    ->whereIn('status', [
                        ProductBatch::STATUS_ACTIVE,
                        ProductBatch::STATUS_SEASONAL,
                    ])
                    ->sum('quantity');

                $branchProduct->update([
                    'stock' => (float) $stock,
                ]);
            });
    }

    private function baseTargetStock(BranchProduct $branchProduct, int $branchIndex, int $index): int
    {
        $category = $branchProduct->product?->category?->name ?? '';

        return match ($category) {
            'Refrescos' => 620 + (($branchIndex + $index) % 90),
            'Papas' => 520 + (($branchIndex + $index) % 80),
            'Lacteos' => 360 + (($branchIndex + $index) % 60),
            'Quimicos' => 220 + (($branchIndex + $index) % 40),
            'Vinos' => 130 + (($branchIndex + $index) % 30),
            default => 220 + (($branchIndex + $index) % 45),
        };
    }

    private function monthlyTargetStock(BranchProduct $branchProduct, int $branchIndex, int $index): int
    {
        $category = $branchProduct->product?->category?->name ?? '';

        return match ($category) {
            'Refrescos' => 180 + (($branchIndex + $index) % 35),
            'Papas' => 150 + (($branchIndex + $index) % 30),
            'Lacteos' => 110 + (($branchIndex + $index) % 24),
            'Quimicos' => 68 + (($branchIndex + $index) % 18),
            'Vinos' => 36 + (($branchIndex + $index) % 12),
            default => 70 + (($branchIndex + $index) % 18),
        };
    }

    private function dateStockMovement(StockMovement $movement, $saleTime): void
    {
        $movement->update([
            'created_at' => $saleTime,
            'updated_at' => $saleTime,
        ]);

        $movement->batches()->update([
            'created_at' => $saleTime,
            'updated_at' => $saleTime,
        ]);
    }

    private function syncBranchInventory(Branch $branch): void
    {
        BranchProduct::query()
            ->where('branch_id', $branch->id)
            ->each(function (BranchProduct $branchProduct) use ($branch) {
                BranchInventory::updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'product_id' => $branchProduct->product_id,
                    ],
                    [
                        'current_stock' => (int) floor((float) $branchProduct->stock),
                        'minimum_stock' => (int) floor((float) $branchProduct->min_stock),
                        'maximum_stock' => max(
                            (int) floor((float) $branchProduct->min_stock * 4),
                            (int) floor((float) $branchProduct->stock + 12)
                        ),
                    ]
                );
            });
    }
}
