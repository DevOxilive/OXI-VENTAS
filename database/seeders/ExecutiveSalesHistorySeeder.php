<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchInventory;
use App\Models\BranchProduct;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class ExecutiveSalesHistorySeeder extends Seeder
{
    private const SEEDED_FOLIO_PREFIX = 'SEED-CEO-';
    private const HISTORY_DAYS = 365;

    public function run(): void
    {
        Event::fakeFor(function () {
            $this->clearSeededSales();

            $stockService = app(StockMovementService::class);
            $branches = Branch::query()
                ->where('active', true)
                ->orderBy('name')
                ->get();

            $paymentMethods = PaymentMethod::query()
                ->where('active', true)
                ->orderBy('id')
                ->get();

            $customers = Customer::query()
                ->where('active', true)
                ->orderBy('id')
                ->get();

            $salesEmployees = Employee::query()
                ->where('employment_status', 'Activo')
                ->whereHas('position.department', fn ($department) => $department->where('name', 'Ventas'))
                ->orderBy('id')
                ->get();

            if ($branches->isEmpty() || $paymentMethods->isEmpty() || $salesEmployees->isEmpty()) {
                return;
            }

            $branches->each(function (Branch $branch, int $branchIndex) use (
                $stockService,
                $paymentMethods,
                $salesEmployees,
                $customers,
            ) {
                $this->seedBranchSales(
                    branch: $branch,
                    branchIndex: $branchIndex,
                    stockService: $stockService,
                    paymentMethods: $paymentMethods,
                    salesEmployees: $salesEmployees,
                    customers: $customers,
                );

                $this->syncBranchInventory($branch);
            });
        });
    }

    private function seedBranchSales(
        Branch $branch,
        int $branchIndex,
        StockMovementService $stockService,
        Collection $paymentMethods,
        Collection $salesEmployees,
        Collection $customers,
    ): void {
        $profile = $this->branchProfile($branch->slug, $branchIndex);
        $employeePool = $salesEmployees
            ->slice($branchIndex % max(1, $salesEmployees->count()), 2)
            ->values();

        if ($employeePool->isEmpty()) {
            $employeePool = $salesEmployees->values();
        }

        $folioCounter = 1;

        foreach (range(self::HISTORY_DAYS - 1, 0) as $daysAgo) {
            $saleDate = now()->subDays($daysAgo);

            $dailySales = $this->salesCountForDay($saleDate, $profile);

            foreach (range(1, $dailySales) as $saleIndex) {
                $branchProducts = BranchProduct::query()
                    ->with([
                        'product.category',
                        'product.barcodes:id,product_id,code',
                    ])
                    ->where('branch_id', $branch->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->where('stock', '>', 0)
                    ->whereHas('product', fn ($query) => $query->where('active', true))
                    ->get();

                if ($branchProducts->isEmpty()) {
                    continue;
                }

                $saleTime = $saleDate->copy()->setTime(
                    9 + (($saleIndex + $branchIndex) % 10),
                    [5, 15, 25, 35, 45, 55][($saleIndex + $daysAgo + $branchIndex) % 6],
                );

                $employee = $employeePool[($saleIndex + $daysAgo) % $employeePool->count()];
                $paymentMethod = $this->resolvePaymentMethod($paymentMethods, $saleIndex + $daysAgo);

                $sale = Sale::create([
                    'folio' => self::SEEDED_FOLIO_PREFIX . str_pad((string) ($branchIndex + 1), 2, '0', STR_PAD_LEFT) . '-'
                        . str_pad((string) $folioCounter, 4, '0', STR_PAD_LEFT),
                    'date' => $saleTime,
                    'employee_id' => $employee->id,
                    'customer_id' => $this->resolveCustomer($customers, $saleIndex + $daysAgo)?->id,
                    'branch_id' => $branch->id,
                    'payment_method_id' => $paymentMethod->id,
                    'total' => 0,
                    'cash_received' => 0,
                    'change_due' => 0,
                    'status' => 'completed',
                    'created_at' => $saleTime,
                    'updated_at' => $saleTime,
                ]);

                $total = $this->createSaleDetails(
                    sale: $sale,
                    branchProducts: $this->selectProductsForSale(
                        branchProducts: $branchProducts,
                        branchIndex: $branchIndex,
                        saleIndex: $saleIndex,
                    ),
                    branchIndex: $branchIndex,
                    saleIndex: $saleIndex,
                    saleTime: $saleTime,
                    stockService: $stockService,
                );

                if ($total <= 0) {
                    $sale->delete();
                    continue;
                }

                $cashReceived = $paymentMethod->name === 'Cash'
                    ? $this->cashReceivedForTotal($total)
                    : round($total, 2);

                $sale->update([
                    'total' => round($total, 2),
                    'cash_received' => $cashReceived,
                    'change_due' => round($cashReceived - $total, 2),
                    'date' => $saleTime,
                    'created_at' => $saleTime,
                    'updated_at' => $saleTime,
                ]);

                $folioCounter++;
            }
        }
    }

    private function createSaleDetails(
        Sale $sale,
        Collection $branchProducts,
        int $branchIndex,
        int $saleIndex,
        $saleTime,
        StockMovementService $stockService,
    ): float {
        $total = 0.0;

        foreach ($branchProducts as $productIndex => $branchProduct) {
            $freshBranchProduct = BranchProduct::query()
                ->with([
                    'product.category',
                    'product.barcodes:id,product_id,code',
                ])
                ->find($branchProduct->id);

            if (!$freshBranchProduct || (float) $freshBranchProduct->stock <= 0) {
                continue;
            }

            $quantity = $this->quantityForSaleItem($freshBranchProduct, $productIndex);

            if ($quantity <= 0) {
                continue;
            }

            $discountPercentage = $this->discountForItem(
                branchIndex: $branchIndex,
                saleIndex: $saleIndex,
                productIndex: $productIndex,
            );
            $originalUnitPrice = round((float) $freshBranchProduct->product?->sale_price, 2);
            $discountAmount = round(($originalUnitPrice * $discountPercentage) / 100, 2);
            $unitPrice = round(max(0, $originalUnitPrice - $discountAmount), 2);
            $subtotal = round($quantity * $unitPrice, 2);

            $movement = $stockService->move(
                branchProduct: $freshBranchProduct,
                type: StockMovement::TYPE_OUT,
                reason: StockMovement::REASON_SALE,
                quantity: (float) $quantity,
                notes: ExecutiveInventoryReplenishmentSeeder::MOVEMENT_NOTE_PREFIX . ' venta anual para analitica comercial.',
                userId: null,
                batches: [],
                batchAllocationMethod: StockMovementBatch::ALLOCATION_MANUAL,
                manualBatches: $this->allocateBatchesForSale($freshBranchProduct, $quantity),
            );

            $this->dateStockMovement($movement, $saleTime);

            SaleDetail::create([
                'sale_id' => $sale->id,
                'product_id' => $freshBranchProduct->product_id,
                'barcode_id' => $freshBranchProduct->product?->barcodes?->first()?->id,
                'lot_id' => null,
                'quantity' => $quantity,
                'original_unit_price' => $originalUnitPrice,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'created_at' => $saleTime,
                'updated_at' => $saleTime,
            ]);

            $total += $subtotal;
        }

        return round($total, 2);
    }

    private function clearSeededSales(): void
    {
        $seededSales = Sale::query()
            ->where('folio', 'like', self::SEEDED_FOLIO_PREFIX . '%')
            ->pluck('id');

        if ($seededSales->isEmpty()) {
            return;
        }

        SaleDetail::query()
            ->whereIn('sale_id', $seededSales)
            ->delete();

        Sale::query()
            ->whereIn('id', $seededSales)
            ->delete();
    }

    private function branchProfile(string $slug, int $branchIndex): array
    {
        $profiles = [
            'cecilia' => ['base' => 3, 'weekend' => 1.24],
            'diana' => ['base' => 3, 'weekend' => 1.16],
            'lago' => ['base' => 2, 'weekend' => 1.10],
            'ajusco' => ['base' => 2, 'weekend' => 1.04],
        ];

        return $profiles[$slug] ?? [
            'base' => 2 + ($branchIndex % 2),
            'weekend' => 1.10,
        ];
    }

    private function salesCountForDay($date, array $profile): int
    {
        $weekdayBoost = match ($date->dayOfWeekIso) {
            5 => 1.15,
            6, 7 => $profile['weekend'],
            default => 1.0,
        };

        $weeklyWave = (($date->dayOfYear % 4) * 0.35);
        $base = ($profile['base'] + $weeklyWave) * $weekdayBoost;

        return 1;
    }

    private function resolvePaymentMethod(Collection $paymentMethods, int $seed)
    {
        $weightedIndex = $seed % 10;

        if ($weightedIndex <= 4) {
            return $paymentMethods->firstWhere('name', 'Cash') ?? $paymentMethods->first();
        }

        if ($weightedIndex <= 7) {
            return $paymentMethods->firstWhere('name', 'Card') ?? $paymentMethods->first();
        }

        return $paymentMethods->firstWhere('name', 'Transfer') ?? $paymentMethods->first();
    }

    private function resolveCustomer(Collection $customers, int $seed): ?Customer
    {
        if ($customers->isEmpty()) {
            return null;
        }

        if ($seed % 10 <= 5) {
            return $customers->firstWhere('name', 'Cliente mostrador') ?? $customers->first();
        }

        return $customers[$seed % $customers->count()];
    }

    private function selectProductsForSale(Collection $branchProducts, int $branchIndex, int $saleIndex): Collection
    {
        $itemCount = min(
            $branchProducts->count(),
            1 + (($branchIndex + $saleIndex) % 2),
        );

        return $branchProducts
            ->sortByDesc(function ($branchProduct) use ($branchIndex, $saleIndex) {
                $category = $branchProduct->product?->category?->name ?? '';
                $categoryWeight = match ($category) {
                    'Refrescos' => 150,
                    'Papas' => 120,
                    'Lacteos' => 95,
                    'Quimicos' => 75,
                    'Vinos' => 55,
                    default => 40,
                };

                return $categoryWeight
                    + (float) $branchProduct->stock
                    - (($branchProduct->id + $branchIndex + $saleIndex) % 17);
            })
            ->take($itemCount)
            ->values();
    }

    private function quantityForSaleItem(BranchProduct $branchProduct, int $productIndex): int
    {
        $category = $branchProduct->product?->category?->name ?? '';
        $available = (int) floor((float) $branchProduct->stock);

        if ($available <= 0) {
            return 0;
        }

        $planned = match ($category) {
            'Refrescos' => 2 + ($productIndex % 3),
            'Papas' => 2 + (($productIndex + 1) % 3),
            'Lacteos' => 1 + ($productIndex % 2),
            'Quimicos' => 1 + ($productIndex % 2),
            'Vinos' => 1,
            default => 1,
        };

        return max(1, min($planned, $available, 4));
    }

    private function discountForItem(int $branchIndex, int $saleIndex, int $productIndex): float
    {
        $key = ($branchIndex + $saleIndex + $productIndex) % 10;

        return match (true) {
            $key === 0 => 12.0,
            $key <= 2 => 8.0,
            $key <= 4 => 5.0,
            default => 0.0,
        };
    }

    private function allocateBatchesForSale(BranchProduct $branchProduct, int $quantity): array
    {
        $remaining = $quantity;

        return ProductBatch::query()
            ->where('branch_product_id', $branchProduct->id)
            ->whereIn('status', [
                ProductBatch::STATUS_ACTIVE,
                ProductBatch::STATUS_SEASONAL,
            ])
            ->where('quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiration_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get()
            ->reduce(function (array $carry, ProductBatch $batch) use (&$remaining) {
                if ($remaining <= 0) {
                    return $carry;
                }

                $available = (float) $batch->quantity;
                $take = min($available, $remaining);

                if ($take <= 0) {
                    return $carry;
                }

                $carry[] = [
                    'id' => $batch->id,
                    'quantity' => $take,
                ];

                $remaining -= $take;

                return $carry;
            }, []);
    }

    private function cashReceivedForTotal(float $total): float
    {
        $rounded = ceil($total / 50) * 50;

        if ($rounded < $total) {
            $rounded = ceil($total / 100) * 100;
        }

        return round(max($rounded, $total), 2);
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
