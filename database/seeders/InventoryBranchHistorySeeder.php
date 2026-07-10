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
use Illuminate\Support\Facades\Hash;

class InventoryBranchHistorySeeder extends Seeder
{
    private const LEGACY_PRODUCT_PREFIX = 'Demo Reporte Inventario';
    private const BARCODE_PREFIX = 'RPTINV';
    private const EXTRA_HISTORY_ROUNDS = 10;

    public function run(): void
    {
        Event::fakeFor(function () {
            DB::transaction(function () {
                $stockService = app(StockMovementService::class);
                $categories = $this->categories();

                Branch::query()
                    ->where('active', true)
                    ->orderBy('name')
                    ->get()
                    ->each(fn (Branch $branch) => $this->seedBranch(
                        branch: $branch,
                        stockService: $stockService,
                        categories: $categories,
                    ));
            });
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
        return collect([
            ['name' => 'Ana Caducidades', 'email' => 'ana.caducidades@oxilive.com.mx'],
            ['name' => 'Bruno Danados', 'email' => 'bruno.danados@oxilive.com.mx'],
            ['name' => 'Carla Ajustes', 'email' => 'carla.ajustes@oxilive.com.mx'],
            ['name' => 'Diego Entradas', 'email' => 'diego.entradas@oxilive.com.mx'],
            ['name' => 'Elena Ventas', 'email' => 'elena.ventas@oxilive.com.mx'],
        ])->map(fn (array $user) => User::updateOrCreate(
            ['email' => $user['email']],
            [
                'name' => $user['name'],
                'password' => Hash::make('password'),
                'branch_id' => null,
            ]
        ))->values();
    }

    private function categories()
    {
        return $this->catalog()
            ->pluck('category')
            ->unique()
            ->values()
            ->mapWithKeys(fn (string $name) => [
            $name => Category::firstOrCreate(['name' => $name]),
        ]);
    }

    private function product(int $index, $categories): Product
    {
        $catalogItem = $this->catalog()[$index] ?? $this->catalog()->first();
        $category = $categories[$catalogItem['category']];
        $cost = (float) $catalogItem['cost'];
        $salePrice = (float) $catalogItem['sale_price'];

        return Product::updateOrCreate(
            ['name' => $catalogItem['name']],
            [
                'description' => $catalogItem['description'],
                'cost' => $cost,
                'sale_price' => $salePrice,
                'margin_percentage' => $this->calculateMarginPercentage($cost, $salePrice),
                'unit' => $catalogItem['unit'],
                'category_id' => $category->id,
                'active' => true,
            ]
        );
    }

    private function catalog()
    {
        return collect([
            ['category' => 'Refrescos', 'name' => 'Coca-Cola 600 ml', 'description' => 'Refresco Coca-Cola sabor original en presentacion individual de 600 ml.', 'cost' => 13.50, 'sale_price' => 19.00, 'unit' => 'pieza'],
            ['category' => 'Refrescos', 'name' => 'Pepsi 600 ml', 'description' => 'Refresco Pepsi cola en botella individual de 600 ml.', 'cost' => 12.80, 'sale_price' => 18.00, 'unit' => 'pieza'],
            ['category' => 'Refrescos', 'name' => 'Sprite 600 ml', 'description' => 'Refresco Sprite sabor lima-limon en botella de 600 ml.', 'cost' => 12.90, 'sale_price' => 18.50, 'unit' => 'pieza'],
            ['category' => 'Refrescos', 'name' => 'Fanta Naranja 600 ml', 'description' => 'Refresco Fanta de naranja en botella individual de 600 ml.', 'cost' => 12.90, 'sale_price' => 18.50, 'unit' => 'pieza'],
            ['category' => 'Refrescos', 'name' => 'Sidral Mundet 600 ml', 'description' => 'Refresco Sidral Mundet de manzana en botella individual de 600 ml.', 'cost' => 13.20, 'sale_price' => 19.00, 'unit' => 'pieza'],
            ['category' => 'Vinos', 'name' => 'L.A. Cetto Cabernet Sauvignon 750 ml', 'description' => 'Vino tinto mexicano Cabernet Sauvignon en botella de 750 ml.', 'cost' => 168.00, 'sale_price' => 229.00, 'unit' => 'pieza'],
            ['category' => 'Vinos', 'name' => 'L.A. Cetto Blanc de Blancs 750 ml', 'description' => 'Vino blanco seco L.A. Cetto Blanc de Blancs en botella de 750 ml.', 'cost' => 155.00, 'sale_price' => 215.00, 'unit' => 'pieza'],
            ['category' => 'Vinos', 'name' => 'Mateus Rose 750 ml', 'description' => 'Vino rosado Mateus en botella de 750 ml.', 'cost' => 149.00, 'sale_price' => 209.00, 'unit' => 'pieza'],
            ['category' => 'Vinos', 'name' => 'Casillero del Diablo Cabernet Sauvignon 750 ml', 'description' => 'Vino tinto chileno Casillero del Diablo Cabernet Sauvignon.', 'cost' => 198.00, 'sale_price' => 279.00, 'unit' => 'pieza'],
            ['category' => 'Vinos', 'name' => 'Freixenet Carta Nevada 750 ml', 'description' => 'Vino espumoso Freixenet Carta Nevada en botella de 750 ml.', 'cost' => 238.00, 'sale_price' => 329.00, 'unit' => 'pieza'],
            ['category' => 'Papas', 'name' => 'Sabritas Original 45 g', 'description' => 'Papas fritas Sabritas clasicas en bolsa de 45 gramos.', 'cost' => 13.00, 'sale_price' => 18.00, 'unit' => 'pieza'],
            ['category' => 'Papas', 'name' => 'Ruffles Queso 50 g', 'description' => 'Papas Ruffles sabor queso en presentacion de 50 gramos.', 'cost' => 14.20, 'sale_price' => 20.00, 'unit' => 'pieza'],
            ['category' => 'Papas', 'name' => 'Doritos Nacho 61 g', 'description' => 'Botana Doritos Nacho con queso en bolsa de 61 gramos.', 'cost' => 14.80, 'sale_price' => 21.00, 'unit' => 'pieza'],
            ['category' => 'Papas', 'name' => 'Cheetos Torciditos 52 g', 'description' => 'Botana Cheetos Torciditos en presentacion de 52 gramos.', 'cost' => 13.80, 'sale_price' => 19.50, 'unit' => 'pieza'],
            ['category' => 'Papas', 'name' => 'Chips Jalapeno 42 g', 'description' => 'Papas Chips sabor jalapeno en bolsa de 42 gramos.', 'cost' => 15.20, 'sale_price' => 22.00, 'unit' => 'pieza'],
            ['category' => 'Lacteos', 'name' => 'Leche Lala Entera 1 L', 'description' => 'Leche entera Lala en envase de un litro.', 'cost' => 22.50, 'sale_price' => 29.50, 'unit' => 'pieza'],
            ['category' => 'Lacteos', 'name' => 'Leche Santa Clara Deslactosada 1 L', 'description' => 'Leche deslactosada Santa Clara en envase de un litro.', 'cost' => 24.50, 'sale_price' => 32.00, 'unit' => 'pieza'],
            ['category' => 'Lacteos', 'name' => 'Yogurt Yoplait Fresa 1 kg', 'description' => 'Yogurt Yoplait sabor fresa en presentacion de un kilogramo.', 'cost' => 39.00, 'sale_price' => 52.00, 'unit' => 'pieza'],
            ['category' => 'Lacteos', 'name' => 'Queso Panela Lala 400 g', 'description' => 'Queso panela Lala empacado en presentacion de 400 gramos.', 'cost' => 67.00, 'sale_price' => 89.00, 'unit' => 'pieza'],
            ['category' => 'Lacteos', 'name' => 'Crema Alpura 426 ml', 'description' => 'Crema Alpura en envase de 426 mililitros.', 'cost' => 26.50, 'sale_price' => 36.00, 'unit' => 'pieza'],
            ['category' => 'Quimicos', 'name' => 'Cloro Cloralex 950 ml', 'description' => 'Cloro Cloralex desinfectante en presentacion de 950 mililitros.', 'cost' => 14.00, 'sale_price' => 21.00, 'unit' => 'pieza'],
            ['category' => 'Quimicos', 'name' => 'Pinol Original 828 ml', 'description' => 'Limpiador Pinol original en botella de 828 mililitros.', 'cost' => 21.50, 'sale_price' => 31.00, 'unit' => 'pieza'],
            ['category' => 'Quimicos', 'name' => 'Fabuloso Lavanda 1 L', 'description' => 'Limpiador multiusos Fabuloso aroma lavanda en presentacion de un litro.', 'cost' => 19.50, 'sale_price' => 28.00, 'unit' => 'pieza'],
            ['category' => 'Quimicos', 'name' => 'Suavitel Fresca Primavera 850 ml', 'description' => 'Suavizante Suavitel aroma fresca primavera en botella de 850 mililitros.', 'cost' => 17.50, 'sale_price' => 25.00, 'unit' => 'pieza'],
            ['category' => 'Quimicos', 'name' => 'Detergente Roma 1 kg', 'description' => 'Detergente en polvo Roma en bolsa de un kilogramo.', 'cost' => 36.00, 'sale_price' => 49.00, 'unit' => 'pieza'],
        ])->values();
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
        $barcode = self::BARCODE_PREFIX . str_pad((string) $index, 6, '0', STR_PAD_LEFT);

        Barcode::updateOrCreate(
            ['code' => $barcode],
            [
                'product_id' => $product->id,
                'type' => 'inventory-report-demo',
                'base_quantity' => 1,
                'active' => true,
            ]
        );

        return BranchProduct::updateOrCreate(
            [
                'branch_id' => $branch->id,
                'product_id' => $product->id,
            ],
            [
                'barcode' => $barcode,
                'stock' => 0,
                'min_stock' => 8 + ($index % 12),
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
