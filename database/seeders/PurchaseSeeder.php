<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Employee;
use App\Models\Product;
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
}
