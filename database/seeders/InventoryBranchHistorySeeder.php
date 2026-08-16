<?php

namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class InventoryBranchHistorySeeder extends Seeder
{
    private const LEGACY_PRODUCT_PREFIX = 'Demo Reporte Inventario';
    private const BARCODE_PREFIX = 'RPTINV';
    private const EXTRA_HISTORY_ROUNDS = 10;

    private $catalogCache = null;

    public function run(): void
    {
        Event::fakeFor(function () {
            $stockService = app(StockMovementService::class);
            $categories = $this->categories();

            $this->deactivateProductsOutsideCatalog();

            Branch::query()
                ->where('active', true)
                ->orderBy('name')
                ->get()
                ->each(fn (Branch $branch) => DB::transaction(fn () => $this->seedBranch(
                    branch: $branch,
                    stockService: $stockService,
                    categories: $categories,
                )));
        });
    }

    private function seedBranch(Branch $branch, StockMovementService $stockService, $categories): void
    {
        $users = $this->reportUsers($branch);
        $catalog = $this->catalog();

        $this->clearLegacyDemoInventory($branch);

        foreach ($catalog as $index => $_productData) {
            $position = $index + 1;
            $product = $this->product($index, $categories);
            $branchProduct = $this->branchProduct($branch, $product, $position);

            $this->clearDemoInventory($branchProduct);

            $entryUser = $users[$position % $users->count()];
            $entryDate = now()
                ->subMonths(12)
                ->addDays(($position * 3) % 28)
                ->setTime(8 + ($position % 3), 0);
            $batches = $this->batchesForProduct($position, $entryDate);
            $entryQuantity = collect($batches)->sum('quantity');

            $entryMovement = $stockService->move(
                branchProduct: $branchProduct,
                type: StockMovement::TYPE_IN,
                reason: StockMovement::REASON_PURCHASE,
                quantity: (float) $entryQuantity,
                notes: $position === 15
                    ? 'Caso auditoria: ingreso inicial de 15 piezas, despues corregido a 14.'
                    : 'Seeder reportes: entrada obligatoria con lotes.',
                userId: $entryUser->id,
                batches: $batches,
            );

            $this->dateMovement($entryMovement, $entryDate);
            $this->dateBatches($branchProduct, $batches, $entryDate);

            if ($position === 15) {
                $this->createCorrectionCase($stockService, $branchProduct, $users, $entryDate);
                continue;
            }

            if ($position % 10 === 0) {
                $this->consumeAllStock($stockService, $branchProduct, $users, $position);
                continue;
            }

            if ($position % 4 === 0) {
                $this->createExpiredMovement($stockService, $branchProduct, $users, $position);
            }

            if ($position % 5 === 0) {
                $this->createDamagedMovement($stockService, $branchProduct, $users, $position);
            }

            if ($position % 6 === 0) {
                $this->createManualAdjustment($stockService, $branchProduct, $users, $position);
            }

            if ($position % 7 === 0) {
                $this->createSaleMovement($stockService, $branchProduct, $users, $position);
            }

            $this->createOperationalTrail(
                stockService: $stockService,
                branchProduct: $branchProduct,
                users: $users,
                index: $position,
                entryDate: $entryDate,
            );
        }

        $this->syncBranchInventory($branch);
    }

    private function reportUsers(Branch $branch)
    {
        return User::query()
            ->whereIn('email', [
                'ana.lilia@oxilive.com.mx',
                'laura@oxilive.com.mx',
                'blanca@oxilive.com.mx',
                'diana@oxilive.com.mx',
                'rodrigo@oxilive.com.mx',
            ])
            ->get()
            ->values();
    }

    private function categories()
    {
        return $this->catalog()
            ->pluck('category')
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $name) => [
                $name => ProductCategorySeeder::resolveLegacyCategory($name) ?? Category::firstOrCreate(['name' => $name]),
            ]);
    }

    private function product(int $index, $categories): Product
    {
        $catalogItem = $this->catalog()[$index] ?? $this->catalog()->first();
        $category = $categories[$catalogItem['category']];
        $cost = (float) $catalogItem['cost'];
        $salePrice = (float) $catalogItem['sale_price'];
        $inventoryUnit = $catalogItem['inventory_unit'] ?? ($catalogItem['unit'] === 'kg' ? 'kg' : 'pza');
        $piecesPerBox = $catalogItem['pieces_per_box'] ?? null;
        $hasBoxPresentation = $inventoryUnit === 'pza' && (int) $piecesPerBox > 1;

        return Product::updateOrCreate(
            ['name' => $catalogItem['name']],
            [
                'description' => $catalogItem['description'],
                'cost' => $cost,
                'sale_price' => $salePrice,
                'margin_percentage' => $this->calculateMarginPercentage($cost, $salePrice),
                'unit' => $catalogItem['unit'],
                'inventory_unit' => $inventoryUnit,
                'pieces_per_box' => $hasBoxPresentation ? (int) $piecesPerBox : null,
                'has_box_presentation' => $hasBoxPresentation,
                'inventory_quantity_mode' => 'base',
                'cost_per_piece' => $inventoryUnit === 'pza' ? $cost : null,
                'sale_price_per_piece' => $inventoryUnit === 'pza' ? $salePrice : null,
                'cost_per_box' => $hasBoxPresentation ? round($cost * (int) $piecesPerBox, 4) : null,
                'sale_price_per_box' => $hasBoxPresentation ? round($salePrice * (int) $piecesPerBox, 4) : null,
                'category_id' => $category->id,
                'active' => true,
            ]
        );
    }

    private function catalog()
    {
        if ($this->catalogCache === null) {
            $this->catalogCache = collect(require database_path('seeders/data/central_products.php'))->values();
        }

        return $this->catalogCache;
    }

    private function calculateMarginPercentage(float $cost, float $salePrice): ?float
    {
        if ($cost <= 0) {
            return null;
        }

        return round((($salePrice - $cost) / $cost) * 100, 2);
    }

    private function branchProduct(Branch $branch, Product $product, int $index): BranchProduct
    {
        $catalogItem = $this->catalog()[$index] ?? [];
        $barcode = $catalogItem['barcode'] ?? self::BARCODE_PREFIX . str_pad((string) $index, 6, '0', STR_PAD_LEFT);

        Barcode::updateOrCreate(
            ['code' => $barcode],
            [
                'product_id' => $product->id,
                'type' => 'principal',
                'base_quantity' => 1,
                'active' => true,
            ]
        );

        foreach ($catalogItem['alternative_barcodes'] ?? [] as $alternativeBarcode) {
            Barcode::updateOrCreate(
                ['code' => $alternativeBarcode],
                [
                    'product_id' => $product->id,
                    'type' => 'alterno',
                    'base_quantity' => 1,
                    'active' => true,
                ]
            );
        }

        if ((bool) $product->has_box_presentation && (int) $product->pieces_per_box > 1) {
            Barcode::updateOrCreate(
                ['code' => $barcode . '-CJ'],
                [
                    'product_id' => $product->id,
                    'type' => 'caja',
                    'base_quantity' => (int) $product->pieces_per_box,
                    'active' => true,
                ]
            );
        }

        return BranchProduct::updateOrCreate(
            [
                'branch_id' => $branch->id,
                'product_id' => $product->id,
            ],
            [
                'barcode' => $barcode,
                'stock' => 0,
                'min_stock' => ($product->inventory_unit === 'kg')
                    ? 4.5 + (($index % 5) * 0.5)
                    : 8 + ($index % 12),
                'tracks_batches' => true,
                'tracks_expiration' => true,
                'status' => BranchProduct::STATUS_ACTIVE,
                'last_restocked_at' => null,
                'inactive_candidate_after_days' => 90,
            ]
        );
    }

    private function batchesForProduct(int $index, $entryDate): array
    {
        $batchCount = 1 + ($index % 3);
        $batches = [];

        for ($lotIndex = 1; $lotIndex <= $batchCount; $lotIndex++) {
            $quantity = $index === 15 && $lotIndex === 1
                ? 15
                : 18 + (($index + $lotIndex) % 23);

            $expiration = match (true) {
                $lotIndex === 1 && $index % 4 === 0 => now()->subDays(4 + ($index % 45))->toDateString(),
                $lotIndex === 1 && $index % 3 === 0 => now()->addDays(7 + ($index % 21))->toDateString(),
                $lotIndex === 1 => $entryDate->copy()->addMonths(7 + ($index % 5))->toDateString(),
                $lotIndex === 2 => $entryDate->copy()->addMonths(9 + ($index % 6))->toDateString(),
                default => $entryDate->copy()->addMonths(13 + ($index % 8))->toDateString(),
            };

            $receivedAt = $entryDate
                ->copy()
                ->addDays($lotIndex * 2)
                ->toDateString();

            $batches[] = [
                'lot_number' => 'RPT-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '-' . $lotIndex,
                'expiration_date' => $expiration,
                'received_at' => $receivedAt,
                'quantity' => (float) $quantity,
                'supplier' => 'Proveedor demo reportes ' . (($index % 5) + 1),
            ];
        }

        return $batches;
    }

    private function createCorrectionCase(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        $entryDate
    ): void {
        $batch = $this->firstAvailableBatch($branchProduct);

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_ADJUSTMENT,
            reason: StockMovement::REASON_INVENTORY_DIFFERENCE,
            quantity: -1,
            notes: 'Caso auditoria: se ingresaron 15 piezas, conteo fisico corrigio a 14.',
            userId: $users[2]->id,
            manualBatches: [[
                'id' => $batch->id,
                'quantity' => 1,
            ]],
        );

        $this->dateMovement($movement, $entryDate->copy()->addHours(3));
    }

    private function consumeAllStock(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index
    ): void {
        $availableBatches = $this->availableManualBatches($branchProduct);
        $quantity = $availableBatches->sum('quantity');

        if ($quantity <= 0) {
            return;
        }

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_OUT,
            reason: StockMovement::REASON_SALE,
            quantity: (float) $quantity,
            notes: 'Seeder reportes: producto agotado para prueba de ultima entrada.',
            userId: $users[4]->id,
            manualBatches: $availableBatches->map(fn ($batch) => [
                'id' => $batch->id,
                'quantity' => (float) $batch->quantity,
            ])->values()->all(),
        );

        $this->dateMovement($movement, now()->subDays(5 + ($index % 35))->setTime(17, 0));
    }

    private function createExpiredMovement(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index
    ): void {
        $batch = $this->firstAvailableBatch($branchProduct, expired: true) ?? $this->firstAvailableBatch($branchProduct);

        if (!$batch) {
            return;
        }

        $quantity = min(3 + ($index % 4), (float) $batch->quantity);

        if ($quantity <= 0) {
            return;
        }

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_OUT,
            reason: StockMovement::REASON_EXPIRED,
            quantity: (float) $quantity,
            notes: 'Seeder reportes: salida por producto caducado.',
            userId: $users[0]->id,
            manualBatches: [[
                'id' => $batch->id,
                'quantity' => (float) $quantity,
            ]],
        );

        $this->dateMovement($movement, now()->subDays(2 + ($index % 45))->setTime(13, 0));
    }

    private function createDamagedMovement(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index
    ): void {
        $batch = $this->firstAvailableBatch($branchProduct);

        if (!$batch) {
            return;
        }

        $quantity = min(2 + ($index % 3), (float) $batch->quantity);

        if ($quantity <= 0) {
            return;
        }

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_OUT,
            reason: StockMovement::REASON_DAMAGED,
            quantity: (float) $quantity,
            notes: 'Seeder reportes: salida por producto danado.',
            userId: $users[1]->id,
            manualBatches: [[
                'id' => $batch->id,
                'quantity' => (float) $quantity,
            ]],
        );

        $this->dateMovement($movement, now()->subDays(3 + ($index % 55))->setTime(15, 0));
    }

    private function createManualAdjustment(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index
    ): void {
        $batch = $this->firstAvailableBatch($branchProduct);

        if (!$batch) {
            return;
        }

        $quantity = min(1 + ($index % 2), (float) $batch->quantity);

        if ($quantity <= 0) {
            return;
        }

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_ADJUSTMENT,
            reason: StockMovement::REASON_INVENTORY_DIFFERENCE,
            quantity: -1 * (float) $quantity,
            notes: 'Seeder reportes: ajuste manual por diferencia de inventario.',
            userId: $users[2]->id,
            manualBatches: [[
                'id' => $batch->id,
                'quantity' => (float) $quantity,
            ]],
        );

        $this->dateMovement($movement, now()->subDays(4 + ($index % 65))->setTime(11, 0));
    }

    private function createSaleMovement(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index
    ): void {
        $batch = $this->firstAvailableBatch($branchProduct);

        if (!$batch) {
            return;
        }

        $quantity = min(2 + ($index % 4), (float) $batch->quantity);

        if ($quantity <= 0) {
            return;
        }

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_OUT,
            reason: StockMovement::REASON_SALE,
            quantity: (float) $quantity,
            notes: 'Seeder reportes: salida por venta para historial operativo.',
            userId: $users[4]->id,
            manualBatches: [[
                'id' => $batch->id,
                'quantity' => (float) $quantity,
            ]],
        );

        $this->dateMovement($movement, now()->subDays(6 + ($index % 50))->setTime(16, 0));
    }

    private function createOperationalTrail(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index,
        $entryDate
    ): void {
        for ($round = 1; $round <= self::EXTRA_HISTORY_ROUNDS; $round++) {
            $eventDate = $entryDate
                ->copy()
                ->addDays(($round * 32) + (($index + $round) % 11));

            if ($eventDate->isFuture()) {
                $eventDate = now()->subDays(($index + $round) % 21);
            }

            if ($index % 9 === 0 && $round === self::EXTRA_HISTORY_ROUNDS) {
                $this->createFollowUpPurchase(
                    stockService: $stockService,
                    branchProduct: $branchProduct,
                    users: $users,
                    index: $index,
                    round: $round,
                    entryDate: $entryDate,
                );
            }

            $batch = $this->firstAvailableBatch($branchProduct);

            if (!$batch) {
                return;
            }

            $availableQuantity = (float) $batch->quantity;

            if ($availableQuantity <= 0) {
                continue;
            }

            $movementType = ($index + $round) % 4;
            $quantity = min(
                1 + (($index + $round) % 3),
                max(1.0, $availableQuantity)
            );

            if ($quantity <= 0) {
                continue;
            }

            if ($movementType === 0) {
                $movement = $stockService->move(
                    branchProduct: $branchProduct->fresh(),
                    type: StockMovement::TYPE_OUT,
                    reason: StockMovement::REASON_SALE,
                    quantity: (float) $quantity,
                    notes: "Seeder reportes: venta operativa ronda {$round}.",
                    userId: $users[4]->id,
                    manualBatches: [[
                        'id' => $batch->id,
                        'quantity' => (float) $quantity,
                    ]],
                );

                $this->dateMovement($movement, $eventDate->copy()->setTime(10 + ($round % 7), 15));
                continue;
            }

            if ($movementType === 1) {
                $movement = $stockService->move(
                    branchProduct: $branchProduct->fresh(),
                    type: StockMovement::TYPE_OUT,
                    reason: StockMovement::REASON_DAMAGED,
                    quantity: (float) $quantity,
                    notes: "Seeder reportes: baja operativa por dano ronda {$round}.",
                    userId: $users[1]->id,
                    manualBatches: [[
                        'id' => $batch->id,
                        'quantity' => (float) $quantity,
                    ]],
                );

                $this->dateMovement($movement, $eventDate->copy()->setTime(11 + ($round % 6), 30));
                continue;
            }

            if ($movementType === 2) {
                $adjustmentQuantity = min(1.0, $availableQuantity);

                $movement = $stockService->move(
                    branchProduct: $branchProduct->fresh(),
                    type: StockMovement::TYPE_ADJUSTMENT,
                    reason: StockMovement::REASON_INVENTORY_DIFFERENCE,
                    quantity: -1 * (float) $adjustmentQuantity,
                    notes: "Seeder reportes: ajuste ciclico de inventario ronda {$round}.",
                    userId: $users[2]->id,
                    manualBatches: [[
                        'id' => $batch->id,
                        'quantity' => (float) $adjustmentQuantity,
                    ]],
                );

                $this->dateMovement($movement, $eventDate->copy()->setTime(9 + ($round % 8), 45));
                continue;
            }

            $this->createFollowUpPurchase(
                stockService: $stockService,
                branchProduct: $branchProduct,
                users: $users,
                index: $index,
                round: $round,
                entryDate: $entryDate,
            );
        }
    }

    private function createFollowUpPurchase(
        StockMovementService $stockService,
        BranchProduct $branchProduct,
        $users,
        int $index,
        int $round,
        $entryDate
    ): void {
        $restockDate = $entryDate->copy()->addDays(18 + ($round * 32) + ($index % 9))->setTime(8 + ($round % 5), 20);

        if ($restockDate->isFuture()) {
            $restockDate = now()->subDays(($index + $round) % 18)->setTime(8 + ($round % 5), 20);
        }

        $lotNumber = 'RPT-' . str_pad((string) $index, 3, '0', STR_PAD_LEFT) . '-R' . $round;
        $quantity = 10 + (($index + $round) % 12);

        $batch = [[
            'lot_number' => $lotNumber,
            'expiration_date' => $restockDate->copy()->addMonths(5 + ($round % 8))->addDays($index % 20)->toDateString(),
            'received_at' => $restockDate->toDateString(),
            'quantity' => (float) $quantity,
            'supplier' => 'Proveedor demo reportes reposicion ' . (($round % 3) + 1),
        ]];

        $movement = $stockService->move(
            branchProduct: $branchProduct->fresh(),
            type: StockMovement::TYPE_IN,
            reason: StockMovement::REASON_PURCHASE,
            quantity: (float) $quantity,
            notes: "Seeder reportes: reabastecimiento operativo ronda {$round}.",
            userId: $users[3]->id,
            batches: $batch,
        );

        $this->dateMovement($movement, $restockDate);
        $this->dateBatches($branchProduct->fresh(), $batch, $restockDate);
    }

    private function availableManualBatches(BranchProduct $branchProduct)
    {
        return ProductBatch::where('branch_product_id', $branchProduct->id)
            ->where('quantity', '>', 0)
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->get(['id', 'quantity']);
    }

    private function firstAvailableBatch(BranchProduct $branchProduct, bool $expired = false): ?ProductBatch
    {
        return ProductBatch::where('branch_product_id', $branchProduct->id)
            ->where('quantity', '>', 0)
            ->when($expired, fn ($query) => $query->whereDate('expiration_date', '<', today()))
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->first();
    }

    private function dateMovement(StockMovement $movement, $date): void
    {
        $movement->update([
            'created_at' => $date,
            'updated_at' => $date,
        ]);
    }

    private function dateBatches(BranchProduct $branchProduct, array $batches, $date): void
    {
        foreach ($batches as $batch) {
            ProductBatch::where('branch_product_id', $branchProduct->id)
                ->where('lot_number', $batch['lot_number'])
                ->update([
                    'received_at' => $batch['received_at'],
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
        }

        $branchProduct->update([
            'last_restocked_at' => $date,
        ]);
    }

    private function clearDemoInventory(BranchProduct $branchProduct): void
    {
        $movementIds = StockMovement::where('branch_product_id', $branchProduct->id)->pluck('id');

        StockMovementBatch::whereIn('stock_movement_id', $movementIds)->delete();
        StockMovement::whereIn('id', $movementIds)->delete();
        ProductBatch::where('branch_product_id', $branchProduct->id)->delete();

        $branchProduct->update([
            'stock' => 0,
            'last_restocked_at' => null,
        ]);
    }

    private function clearLegacyDemoInventory(Branch $branch): void
    {
        $legacyNames = [
            'Canula nasal adulto',
            'Solucion salina 500 ml',
            'Mascarilla nebulizador pediatrica',
            'Regulador de oxigeno',
            'Valvula check concentrador',
            'Humidificador reusable',
        ];

        $products = Product::query()
            ->whereIn('name', $legacyNames)
            ->orWhere('name', 'like', self::LEGACY_PRODUCT_PREFIX . '%')
            ->get();

        foreach ($products as $product) {
            $branchProducts = BranchProduct::where('branch_id', $branch->id)
                ->where('product_id', $product->id)
                ->get();

            foreach ($branchProducts as $branchProduct) {
                $this->clearDemoInventory($branchProduct);
                $branchProduct->delete();
            }

            Barcode::where('product_id', $product->id)
                ->where(function ($query) {
                    $query
                        ->where('type', 'demo')
                        ->orWhere('code', 'like', 'DEMO-%');
                })
                ->delete();

            if (!BranchProduct::where('product_id', $product->id)->exists()) {
                $product->delete();
            }
        }
    }

    private function deactivateProductsOutsideCatalog(): void
    {
        $catalogNames = $this->catalog()
            ->pluck('name')
            ->values();

        Product::query()
            ->whereNotIn('name', $catalogNames)
            ->update(['active' => false]);

        BranchProduct::query()
            ->whereHas('product', fn ($query) => $query->whereNotIn('name', $catalogNames))
            ->update(['status' => BranchProduct::STATUS_INACTIVE]);
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
