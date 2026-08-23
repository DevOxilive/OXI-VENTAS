<?php

namespace Database\Seeders;

use App\Models\BranchProduct;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductMovementHistoryDemoSeeder extends Seeder
{
    private const NOTE_PREFIX = 'Seeder historial movimientos:';
    private const DAYS_BACK = 31;
    private const PRODUCT_LIMIT = 8;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->ensureDemoBranchProducts();

            $branchProducts = BranchProduct::query()
                ->with(['product:id,name,cost,cost_per_piece,inventory_unit,unit', 'batches'])
                ->where('status', BranchProduct::STATUS_ACTIVE)
                ->whereHas('product', fn ($query) => $query->where('active', true))
                ->orderBy('id')
                ->limit(self::PRODUCT_LIMIT)
                ->get();

            if ($branchProducts->isEmpty()) {
                $this->command?->warn('No hay productos de sucursal activos para sembrar historial.');
                return;
            }

            $users = User::query()->orderBy('id')->limit(4)->get();
            $userIds = $users->pluck('id')->values();

            if ($userIds->isEmpty()) {
                $this->command?->warn('No hay usuarios disponibles para asignar movimientos.');
                return;
            }

            $this->clearPreviousDemoData($branchProducts);

            $start = now()->startOfDay()->subDays(self::DAYS_BACK - 1);

            foreach ($branchProducts as $productIndex => $branchProduct) {
                $this->seedProductTrail($branchProduct->fresh(['product', 'batches']), $userIds, $start, $productIndex);
            }
        });
    }

    private function ensureDemoBranchProducts(): void
    {
        $activeCount = BranchProduct::query()
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->count();

        if ($activeCount >= self::PRODUCT_LIMIT) {
            return;
        }

        $branch = Branch::query()
            ->where('active', true)
            ->orderBy('id')
            ->first();

        if (! $branch) {
            return;
        }

        Product::query()
            ->where('active', true)
            ->orderBy('id')
            ->limit(self::PRODUCT_LIMIT * 2)
            ->get()
            ->each(function (Product $product) use ($branch, &$activeCount): void {
                if ($activeCount >= self::PRODUCT_LIMIT) {
                    return;
                }

                $branchProduct = BranchProduct::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->first();

                if ($branchProduct) {
                    $wasActive = ! $branchProduct->trashed()
                        && $branchProduct->status === BranchProduct::STATUS_ACTIVE;

                    if ($branchProduct->trashed()) {
                        $branchProduct->restore();
                    }

                    $branchProduct->update([
                        'status' => BranchProduct::STATUS_ACTIVE,
                    ]);

                    if (! $wasActive) {
                        $activeCount++;
                    }

                    return;
                }

                BranchProduct::create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'barcode' => Barcode::query()->where('product_id', $product->id)->orderBy('id')->value('code'),
                    'stock' => $this->quantityForProduct($product, 45),
                    'min_stock' => $this->quantityForProduct($product, 8),
                    'status' => BranchProduct::STATUS_ACTIVE,
                    'tracks_batches' => true,
                    'tracks_expiration' => true,
                    'last_restocked_at' => now()->subDays(self::DAYS_BACK)->toDateString(),
                    'inactive_candidate_after_days' => 90,
                ]);

                $activeCount++;
            });
    }

    private function clearPreviousDemoData($branchProducts): void
    {
        $branchProductIds = $branchProducts->pluck('id');

        StockMovement::query()
            ->whereIn('branch_product_id', $branchProductIds)
            ->where('notes', 'like', self::NOTE_PREFIX . '%')
            ->orderBy('created_at')
            ->get()
            ->groupBy('branch_product_id')
            ->each(function ($movements, int $branchProductId): void {
                $firstMovement = $movements->first();

                BranchProduct::whereKey($branchProductId)->update([
                    'stock' => $firstMovement->previous_stock,
                ]);
            });

        StockMovementBatch::query()
            ->whereHas('stockMovement', fn ($query) => $query
                ->whereIn('branch_product_id', $branchProductIds)
                ->where('notes', 'like', self::NOTE_PREFIX . '%'))
            ->orderByDesc('id')
            ->get()
            ->each(function (StockMovementBatch $movementBatch): void {
                ProductBatch::whereKey($movementBatch->product_batch_id)->update([
                    'quantity' => $movementBatch->previous_batch_quantity,
                ]);
            });

        StockMovement::query()
            ->whereIn('branch_product_id', $branchProductIds)
            ->where('notes', 'like', self::NOTE_PREFIX . '%')
            ->delete();
    }

    private function seedProductTrail(BranchProduct $branchProduct, $userIds, Carbon $start, int $productIndex): void
    {
        $unitCost = (float) ($branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0);
        $batch = $this->demoBatchFor($branchProduct, $start, $productIndex);

        for ($day = 0; $day < self::DAYS_BACK; $day++) {
            $date = $start->copy()
                ->addDays($day)
                ->setTime(8 + (($day + $productIndex) % 10), 5 + (($day * 7 + $productIndex) % 50));

            $pattern = ($day + $productIndex) % 7;
            $userId = $userIds[($day + $productIndex) % $userIds->count()];

            match ($pattern) {
                0 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_IN, StockMovement::REASON_PURCHASE, $this->quantityFor($branchProduct, 12 + ($day % 5)), $unitCost, $userId, $date, 'Compra de reposicion para prueba de historial.'),
                1 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_OUT, StockMovement::REASON_SALE, $this->quantityFor($branchProduct, 2 + ($day % 3)), $unitCost, $userId, $date, 'Venta registrada para prueba de historial.'),
                2 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_IN, StockMovement::REASON_RETURN, $this->quantityFor($branchProduct, 1 + ($day % 2)), $unitCost, $userId, $date, 'Devolucion de producto para prueba de historial.'),
                3 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_OUT, StockMovement::REASON_DAMAGED, $this->quantityFor($branchProduct, 1), $unitCost, $userId, $date, 'Merma por producto dañado para prueba de historial.'),
                4 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_OUT, StockMovement::REASON_EXPIRED, $this->quantityFor($branchProduct, 1), $unitCost, $userId, $date, 'Retiro por caducidad para prueba de historial.'),
                5 => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_ADJUSTMENT, StockMovement::REASON_INVENTORY_DIFFERENCE, $this->quantityFor($branchProduct, ($day % 2 === 0 ? 2 : -1)), $unitCost, $userId, $date, 'Ajuste manual por diferencia de inventario para prueba de historial.'),
                default => $this->createMovement($branchProduct, $batch, StockMovement::TYPE_ADJUSTMENT, StockMovement::REASON_OTHER, 0, $unitCost, $userId, $date, 'Actualizacion de datos de lote. Campos modificados: numero de lote.'),
            };
        }
    }

    private function demoBatchFor(BranchProduct $branchProduct, Carbon $start, int $productIndex): ProductBatch
    {
        $batch = $branchProduct->batches()
            ->where('status', ProductBatch::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();

        if ($batch) {
            return $batch;
        }

        $stock = max(40, (float) $branchProduct->stock);

        return ProductBatch::create([
            'branch_product_id' => $branchProduct->id,
            'lot_number' => 'DEMO-HIST-' . str_pad((string) ($productIndex + 1), 3, '0', STR_PAD_LEFT),
            'expiration_date' => now()->addMonths(4)->toDateString(),
            'initial_quantity' => $stock,
            'quantity' => $stock,
            'supplier' => 'Seeder demo',
            'received_at' => $start->toDateString(),
            'status' => ProductBatch::STATUS_ACTIVE,
            'has_real_lot' => true,
            'entry_type' => 'PURCHASE_BATCH',
        ]);
    }

    private function createMovement(
        BranchProduct $branchProduct,
        ProductBatch $batch,
        string $type,
        string $reason,
        float $quantity,
        float $unitCost,
        int $userId,
        Carbon $date,
        string $note
    ): void {
        $branchProduct->refresh();
        $batch->refresh();

        $previousStock = (float) $branchProduct->stock;
        $previousBatchQuantity = (float) $batch->quantity;
        $movementQuantity = $this->normalizeMovementQuantity($type, $quantity, $previousStock);
        $newStock = $this->newStockFor($type, $previousStock, $movementQuantity);
        $newBatchQuantity = $this->newStockFor($type, $previousBatchQuantity, $movementQuantity);

        $movement = StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'type' => $type,
            'reason' => $reason,
            'quantity' => abs($movementQuantity),
            'unit_cost' => $unitCost,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'user_id' => $userId,
            'notes' => self::NOTE_PREFIX . ' ' . $note,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        if ($movementQuantity !== 0.0) {
            StockMovementBatch::create([
                'stock_movement_id' => $movement->id,
                'product_batch_id' => $batch->id,
                'quantity' => abs($movementQuantity),
                'previous_batch_quantity' => $previousBatchQuantity,
                'new_batch_quantity' => $newBatchQuantity,
                'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
                'created_at' => $date,
                'updated_at' => $date,
            ]);
        }

        $branchProduct->update([
            'stock' => $newStock,
            'last_restocked_at' => $type === StockMovement::TYPE_IN ? $date : $branchProduct->last_restocked_at,
        ]);

        $batch->update([
            'quantity' => $newBatchQuantity,
        ]);
    }

    private function normalizeMovementQuantity(string $type, float $quantity, float $previousStock): float
    {
        if ($quantity === 0.0) {
            return 0.0;
        }

        if ($type === StockMovement::TYPE_OUT) {
            return min(abs($quantity), max(0.0, $previousStock));
        }

        if ($type === StockMovement::TYPE_ADJUSTMENT) {
            return max(-$previousStock, $quantity);
        }

        return abs($quantity);
    }

    private function newStockFor(string $type, float $previous, float $quantity): float
    {
        if ($type === StockMovement::TYPE_OUT) {
            return max(0.0, $previous - abs($quantity));
        }

        if ($type === StockMovement::TYPE_ADJUSTMENT) {
            return max(0.0, $previous + $quantity);
        }

        return $previous + abs($quantity);
    }

    private function quantityFor(BranchProduct $branchProduct, float $quantity): float
    {
        $unit = $branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit;

        return $this->quantityForUnit($unit, $quantity);
    }

    private function quantityForProduct(Product $product, float $quantity): float
    {
        return $this->quantityForUnit($product->inventory_unit ?? $product->unit, $quantity);
    }

    private function quantityForUnit(?string $unit, float $quantity): float
    {
        if ($unit === 'kg') {
            return round($quantity / 3, 3);
        }

        return $quantity;
    }
}
