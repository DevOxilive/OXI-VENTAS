<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Employee;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseSeeder extends Seeder
{
    private const PURCHASE_ID_BASE = 5000;
    private const HISTORY_DAYS = 365;

    public function run(): void
    {
        DB::transaction(function () {
            $branches = Branch::query()
                ->where('active', true)
                ->orderBy('id')
                ->get();

            $suppliers = DB::table('suppliers')
                ->where('active', true)
                ->orderBy('id')
                ->get();

            $employees = Employee::query()
                ->where('employment_status', 'Activo')
                ->whereIn('department', ['Inventario', 'Ventas'])
                ->orderBy('id')
                ->get();

            if ($branches->isEmpty() || $suppliers->isEmpty() || $employees->isEmpty()) {
                return;
            }

            $this->seedCentralPurchaseOrders($branches);

            $purchaseIds = range(self::PURCHASE_ID_BASE, self::PURCHASE_ID_BASE + ($branches->count() * self::HISTORY_DAYS));

            DB::table('purchase_details')
                ->whereIn('purchase_id', $purchaseIds)
                ->delete();

            $purchaseCounter = 0;
            $detailId = self::PURCHASE_ID_BASE * 10;

            foreach ($branches as $branchIndex => $branch) {
                foreach (range(self::HISTORY_DAYS - 1, 0) as $daysAgo) {
                    $purchaseCounter++;
                    $purchaseId = self::PURCHASE_ID_BASE + $purchaseCounter;
                    $purchaseDate = now()
                        ->subDays($daysAgo)
                        ->setTime(7 + ($branchIndex % 3), [10, 25, 40, 55][$purchaseCounter % 4]);

                    $employee = $employees[($purchaseCounter + $branchIndex) % $employees->count()];
                    $supplier = $suppliers[($purchaseCounter + $branchIndex) % $suppliers->count()];
                    $products = $this->productsForPurchase($branch->id, $purchaseCounter);

                    if ($products->isEmpty()) {
                        continue;
                    }

                    $total = 0.0;
                    $details = [];

                    foreach ($products as $productIndex => $product) {
                        $quantity = $this->purchaseQuantity($product->category?->name ?? '', $productIndex, $purchaseCounter);
                        $unitCost = round((float) $product->cost, 2);
                        $subtotal = round($quantity * $unitCost, 2);

                        $details[] = [
                            'id' => $detailId++,
                            'purchase_id' => $purchaseId,
                            'product_id' => $product->id,
                            'lot_id' => null,
                            'quantity' => $quantity,
                            'unit_cost' => $unitCost,
                            'subtotal' => $subtotal,
                            'created_at' => $purchaseDate,
                            'updated_at' => $purchaseDate,
                        ];

                        $total += $subtotal;
                    }

                    DB::table('purchases')->updateOrInsert(
                        ['id' => $purchaseId],
                        [
                            'supplier_id' => $supplier->id,
                            'branch_id' => $branch->id,
                            'employee_id' => $employee->id,
                            'date' => $purchaseDate,
                            'total' => round($total, 2),
                            'status' => 'completed',
                            'created_at' => $purchaseDate,
                            'updated_at' => $purchaseDate,
                        ]
                    );

                    DB::table('purchase_details')->insert($details);
                }
            }
        });
    }

    private function productsForPurchase(int $branchId, int $seed)
    {
        $productIds = BranchProduct::query()
            ->where('branch_id', $branchId)
            ->orderBy('id')
            ->pluck('product_id');

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with('category')
            ->whereIn('id', $productIds)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->skip($seed % 5)
            ->take(4)
            ->values();
    }

    private function purchaseQuantity(string $category, int $productIndex, int $seed): int
    {
        return match ($category) {
            'Refrescos' => 36 + (($seed + $productIndex) % 18),
            'Papas' => 28 + (($seed + $productIndex) % 14),
            'Lacteos' => 18 + (($seed + $productIndex) % 10),
            'Quimicos' => 12 + (($seed + $productIndex) % 8),
            'Vinos' => 6 + (($seed + $productIndex) % 6),
            default => 10 + (($seed + $productIndex) % 8),
        };
    }

    private function seedCentralPurchaseOrders($branches): void
    {
        $orderIds = PurchaseOrder::query()
            ->where('folio', 'like', 'OC-DEMO-%')
            ->pluck('id');

        if ($orderIds->isNotEmpty()) {
            PurchaseOrderItem::query()
                ->whereIn('purchase_order_id', $orderIds)
                ->delete();

            PurchaseOrder::query()
                ->whereIn('id', $orderIds)
                ->delete();
        }

        $userId = DB::table('users')->orderBy('id')->value('id');

        foreach ($branches as $branchIndex => $branch) {
            foreach (range(0, 7) as $sequence) {
                $createdAt = now()
                    ->subDays(($sequence * 14) + $branchIndex)
                    ->setTime(8 + ($sequence % 3), 15);

                $status = match ($sequence) {
                    0 => PurchaseOrder::STATUS_DRAFT,
                    1 => PurchaseOrder::STATUS_GENERATED,
                    default => PurchaseOrder::STATUS_COMPLETED,
                };

                $order = PurchaseOrder::create([
                    'branch_id' => $branch->id,
                    'user_id' => $userId,
                    'completed_by' => $status === PurchaseOrder::STATUS_COMPLETED ? $userId : null,
                    'folio' => sprintf('OC-DEMO-%02d-%02d', $branch->id, $sequence + 1),
                    'source' => PurchaseOrder::SOURCE_CENTRAL,
                    'status' => $status,
                    'purchased_at' => $status === PurchaseOrder::STATUS_COMPLETED
                        ? $createdAt->copy()->addDay()->toDateString()
                        : null,
                    'notes' => 'Orden demo de compra central.',
                    'adjustment_notes' => $status === PurchaseOrder::STATUS_COMPLETED
                        ? 'Ajustes demo por precio y disponibilidad en central.'
                        : null,
                    'generated_at' => $status !== PurchaseOrder::STATUS_DRAFT
                        ? $createdAt->copy()->addHours(2)
                        : null,
                    'completed_at' => $status === PurchaseOrder::STATUS_COMPLETED
                        ? $createdAt->copy()->addDay()->addHours(3)
                        : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                $products = BranchProduct::query()
                    ->with('product.category')
                    ->where('branch_id', $branch->id)
                    ->orderBy('id')
                    ->get()
                    ->skip(($sequence + $branchIndex) % 6)
                    ->take(5)
                    ->values();

                $estimatedTotal = 0.0;
                $actualTotal = 0.0;

                foreach ($products as $productIndex => $branchProduct) {
                    $quantity = $this->purchaseQuantity(
                        $branchProduct->product?->category?->name ?? '',
                        $productIndex,
                        $sequence + $branchIndex
                    );
                    $estimatedPrice = round((float) ($branchProduct->product?->cost ?? 0), 2);
                    $estimatedLineTotal = round($quantity * $estimatedPrice, 2);
                    $isUnavailable = $status !== PurchaseOrder::STATUS_DRAFT
                        && (($sequence + $productIndex + $branchIndex) % 9 === 0);
                    $purchasedQuantity = null;
                    $actualPrice = null;
                    $actualLineTotal = null;
                    $itemStatus = PurchaseOrderItem::STATUS_REQUESTED;

                    if ($status !== PurchaseOrder::STATUS_DRAFT) {
                        $purchasedQuantity = $isUnavailable
                            ? 0
                            : max(1, $quantity - (($sequence + $productIndex) % 3));
                        $actualPrice = $isUnavailable
                            ? 0
                            : round($estimatedPrice + (($sequence + $productIndex) % 4), 2);
                        $actualLineTotal = round($purchasedQuantity * $actualPrice, 2);
                        $itemStatus = $isUnavailable
                            ? PurchaseOrderItem::STATUS_UNAVAILABLE
                            : ($purchasedQuantity !== $quantity || $actualPrice !== $estimatedPrice
                                ? PurchaseOrderItem::STATUS_ADJUSTED
                                : PurchaseOrderItem::STATUS_PURCHASED);
                    }

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'branch_product_id' => $branchProduct->id,
                        'product_id' => $branchProduct->product_id,
                        'current_stock' => $branchProduct->stock,
                        'min_stock' => $branchProduct->min_stock,
                        'requested_quantity' => $quantity,
                        'purchased_quantity' => $purchasedQuantity,
                        'estimated_price' => $estimatedPrice,
                        'estimated_total' => $estimatedLineTotal,
                        'actual_price' => $actualPrice,
                        'actual_total' => $actualLineTotal,
                        'status' => $itemStatus,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    $estimatedTotal += $estimatedLineTotal;
                    $actualTotal += (float) ($actualLineTotal ?? 0);
                }

                $order->update([
                    'estimated_total' => round($estimatedTotal, 2),
                    'actual_total' => round($actualTotal, 2),
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
