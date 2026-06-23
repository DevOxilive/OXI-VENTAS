<?php

namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Branch;
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

class InventoryReportSeeder extends Seeder
{
    private const PRODUCT_COUNT = 100;
    private const PRODUCT_PREFIX = 'Demo Reporte Inventario';
    private const BARCODE_PREFIX = 'RPTINV';

    public function run(): void
    {
        Event::fakeFor(function () {
            DB::transaction(function () {
                $branch = Branch::firstOrCreate(
                    ['slug' => 'cecilia'],
                    ['name' => 'Cecilia', 'active' => true]
                );

                $users = $this->reportUsers($branch);
                $stockService = app(StockMovementService::class);
                $categories = $this->categories();

                $this->clearLegacyDemoInventory($branch);

                for ($index = 1; $index <= self::PRODUCT_COUNT; $index++) {
                    $product = $this->product($index, $categories);
                    $branchProduct = $this->branchProduct($branch, $product, $index);

                    $this->clearDemoInventory($branchProduct);

                    $batches = $this->batchesForProduct($index);
                    $entryUser = $users[$index % $users->count()];
                    $entryDate = now()->subDays(120 - ($index % 70))->setTime(9, 0);
                    $entryQuantity = collect($batches)->sum('quantity');

                    $entryMovement = $stockService->move(
                        branchProduct: $branchProduct,
                        type: StockMovement::TYPE_IN,
                        reason: StockMovement::REASON_PURCHASE,
                        quantity: (float) $entryQuantity,
                        notes: $index === 15
                            ? 'Caso auditoria: ingreso inicial de 15 piezas, despues corregido a 14.'
                            : 'Seeder reportes: entrada obligatoria con lotes.',
                        userId: $entryUser->id,
                        batches: $batches,
                    );

                    $this->dateMovement($entryMovement, $entryDate);
                    $this->dateBatches($branchProduct, $batches, $entryDate);

                    if ($index === 15) {
                        $this->createCorrectionCase($stockService, $branchProduct, $users, $entryDate);
                        continue;
                    }

                    if ($index % 10 === 0) {
                        $this->consumeAllStock($stockService, $branchProduct, $users, $index);
                        continue;
                    }

                    if ($index % 4 === 0) {
                        $this->createExpiredMovement($stockService, $branchProduct, $users, $index);
                    }

                    if ($index % 5 === 0) {
                        $this->createDamagedMovement($stockService, $branchProduct, $users, $index);
                    }

                    if ($index % 6 === 0) {
                        $this->createManualAdjustment($stockService, $branchProduct, $users, $index);
                    }

                    if ($index % 7 === 0) {
                        $this->createSaleMovement($stockService, $branchProduct, $users, $index);
                    }
                }
            });
        });
    }

    private function reportUsers(Branch $branch)
    {
        return collect([
            ['name' => 'Cecilia Reportes - Ana Caducidades', 'email' => 'ana.caducidades@oxi-demo.test'],
            ['name' => 'Cecilia Reportes - Bruno Danados', 'email' => 'bruno.danados@oxi-demo.test'],
            ['name' => 'Cecilia Reportes - Carla Ajustes', 'email' => 'carla.ajustes@oxi-demo.test'],
            ['name' => 'Cecilia Reportes - Diego Entradas', 'email' => 'diego.entradas@oxi-demo.test'],
            ['name' => 'Cecilia Reportes - Elena Ventas', 'email' => 'elena.ventas@oxi-demo.test'],
        ])->map(fn (array $user) => User::updateOrCreate(
            ['email' => $user['email']],
            [
                'name' => $user['name'],
                'password' => Hash::make('password'),
                'branch_id' => $branch->id,
            ]
        ))->values();
    }

    private function categories()
    {
        return collect([
            'Oxigenoterapia',
            'Desechables',
            'Refacciones',
            'Medicamentos',
            'Accesorios',
        ])->mapWithKeys(fn (string $name) => [
            $name => Category::firstOrCreate(['name' => $name]),
        ]);
    }

    private function product(int $index, $categories): Product
    {
        $categoryNames = $categories->keys()->values();
        $category = $categories[$categoryNames[($index - 1) % $categoryNames->count()]];
        $name = self::PRODUCT_PREFIX . ' ' . str_pad((string) $index, 3, '0', STR_PAD_LEFT);

        return Product::updateOrCreate(
            ['name' => $name],
            [
                'description' => 'Producto demo nutrido para reportes de inventario.',
                'cost' => 25 + ($index * 3.75),
                'sale_price' => 55 + ($index * 5.50),
                'unit' => 'pieza',
                'category_id' => $category->id,
                'active' => true,
            ]
        );
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

    private function batchesForProduct(int $index): array
    {
        $batchCount = 1 + ($index % 3);
        $batches = [];

        for ($lotIndex = 1; $lotIndex <= $batchCount; $lotIndex++) {
            $quantity = $index === 15 && $lotIndex === 1
                ? 15
                : 18 + (($index + $lotIndex) % 23);

            $expiration = match (true) {
                $lotIndex === 1 && $index % 2 === 0 => now()->month((($index % 6) + 1))->day(min(26, 4 + $index))->toDateString(),
                $lotIndex === 1 => now()->addDays(8 + ($index % 22))->toDateString(),
                $lotIndex === 2 => now()->month(7 + ($index % 6))->day(min(26, 3 + $index))->toDateString(),
                default => now()->addMonths(8 + ($index % 8))->day(min(26, 5 + $index))->toDateString(),
            };

            $receivedAt = now()->subDays(140 - (($index + $lotIndex) % 80))->toDateString();

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

        $products = Product::whereIn('name', $legacyNames)->get();

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
}
