<?php

namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\CashRegisterClosure;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseCycle;
use App\Models\PurchaseCycleBranch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReport;
use App\Models\PurchaseReportItem;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesOperationsSeeder extends Seeder
{
    private const FOLIO_PREFIX = 'SEED-VENTAS-';

    private const PURCHASE_PREFIX = 'SEED-COMPRA-';

    private const CLOSURE_PREFIX = 'SEED-CORTE-';

    public function run(): void
    {
        DB::transaction(function () {
            $this->clearPreviousSeedData();

            $branches = Branch::query()->where('active', true)->orderBy('name')->get();
            $customers = Customer::query()->where('active', true)->orderBy('id')->get();
            $paymentMethods = PaymentMethod::query()->where('active', true)->orderBy('id')->get();
            $seller = Employee::query()->where('email', 'margarita@oxilive.com.mx')->first()
                ?? Employee::query()->where('employment_status', 'Activo')->firstOrFail();
            $salesUser = User::query()->where('email', 'margarita@oxilive.com.mx')->first()
                ?? User::query()->firstOrFail();
            $inventoryUser = User::query()->where('email', 'ana.lilia@oxilive.com.mx')->first()
                ?? User::query()->firstOrFail();
            $purchaseCycle = $this->seedPurchaseCycle($branches, $inventoryUser);

            foreach ($branches as $branchIndex => $branch) {
                $branchProducts = BranchProduct::query()
                    ->with('product:id,name,description,cost,sale_price,inventory_unit,pieces_per_box,has_box_presentation,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box')
                    ->where('branch_id', $branch->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->whereHas('product', fn ($query) => $query->where('active', true))
                    ->orderBy('id')
                    ->take(8)
                    ->get();

                if ($branchProducts->isEmpty()) {
                    continue;
                }

                $this->seedCommercialSales($branch, $branchProducts, $seller, $customers, $paymentMethods, $branchIndex);
                $this->seedCashClosure($branch, $salesUser);
                $this->seedPurchaseFlow($purchaseCycle, $branch, $branchProducts, $salesUser, $inventoryUser, $branchIndex);
            }
        });
    }

    private function seedPurchaseCycle($branches, User $inventoryUser): PurchaseCycle
    {
        $createdAt = now()->startOfDay()->setTime(9, 0);

        $cycle = PurchaseCycle::create([
            'folio' => self::PURCHASE_PREFIX . 'CICLO-ACTUAL',
            'status' => PurchaseCycle::STATUS_OPEN,
            'created_by' => $inventoryUser->id,
            'opened_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        foreach ($branches as $branch) {
            PurchaseCycleBranch::create([
                'purchase_cycle_id' => $cycle->id,
                'branch_id' => $branch->id,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        return $cycle;
    }

    private function seedCommercialSales(Branch $branch, $branchProducts, Employee $seller, $customers, $paymentMethods, int $branchIndex): void
    {
        $start = now()->startOfMonth()->subMonths(3)->addDays(2 + $branchIndex);
        $saleNumber = 1;

        for ($month = 0; $month < 4; $month++) {
            for ($day = 0; $day < 4; $day++) {
                $date = $start->copy()
                    ->addMonths($month)
                    ->addDays($day * 6)
                    ->setTime(10 + ($day % 6), 15 + ($branchIndex * 5));

                if ($date->isFuture()) {
                    $date = now()->subDays(1 + $day)->setTime(12 + $day, 20);
                }

                $sale = Sale::create([
                    'folio' => self::FOLIO_PREFIX . $branch->slug . '-' . $date->format('Ym') . '-' . str_pad((string) $saleNumber, 3, '0', STR_PAD_LEFT),
                    'date' => $date,
                    'employee_id' => $seller->id,
                    'customer_id' => $customers->isNotEmpty() ? $customers[($saleNumber + $branchIndex) % $customers->count()]->id : null,
                    'branch_id' => $branch->id,
                    'cash_box_number' => (string) (1 + ($branchIndex % 2)),
                    'payment_method_id' => $paymentMethods[($saleNumber + $branchIndex) % max(1, $paymentMethods->count())]->id,
                    'total' => 0,
                    'cash_received' => 0,
                    'change_due' => 0,
                    'status' => 'completed',
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $total = 0.0;
                foreach ($branchProducts->slice($day % 3, 3)->values() as $detailIndex => $branchProduct) {
                    $product = $branchProduct->product;
                    $presentation = $this->presentationFor($product, $month, $day, $detailIndex);
                    $quantity = $this->visualQuantityFor($presentation, $month, $day, $detailIndex);
                    $piecesPerBox = (int) ($product->pieces_per_box ?: 0);
                    $baseQuantity = $presentation === 'box'
                        ? $quantity * max(1, $piecesPerBox)
                        : $quantity;
                    $unitPrice = $this->unitPriceFor($product, $presentation);
                    $originalUnitPrice = $unitPrice;
                    $discountPercentage = (($saleNumber + $detailIndex) % 7 === 0) ? 5.00 : 0.00;
                    $discountAmount = round(($originalUnitPrice * $quantity) * ($discountPercentage / 100), 2);
                    $subtotal = round(($unitPrice * $quantity) - $discountAmount, 2);

                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_id' => $product->id,
                        'barcode_id' => Barcode::query()->where('product_id', $product->id)->orderBy('id')->value('id'),
                        'quantity' => $quantity,
                        'sale_unit' => $presentation,
                        'base_quantity' => $baseQuantity,
                        'pieces_per_box' => $presentation === 'box' ? $piecesPerBox : null,
                        'original_unit_price' => $originalUnitPrice,
                        'discount_percentage' => $discountPercentage,
                        'discount_amount' => $discountAmount,
                        'unit_price' => $unitPrice,
                        'unit_cost' => (float) ($product->cost_per_piece ?? $product->cost ?? 0),
                        'subtotal' => $subtotal,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $total += $subtotal;
                }

                $sale->update([
                    'total' => round($total, 2),
                    'cash_received' => round($total + (($saleNumber % 3) * 20), 2),
                    'change_due' => round(($saleNumber % 3) * 20, 2),
                ]);

                $saleNumber++;
            }
        }
    }

    private function seedCashClosure(Branch $branch, User $user): void
    {
        $periodStart = now()->startOfWeek()->setTime(8, 0);
        $periodEnd = now()->startOfWeek()->addDays(2)->setTime(21, 0);
        $sales = Sale::query()
            ->where('branch_id', $branch->id)
            ->where('folio', 'like', self::FOLIO_PREFIX . $branch->slug . '-%')
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get();

        if ($sales->isEmpty()) {
            return;
        }

        $salesTotal = round((float) $sales->sum('total'), 2);
        $cardTotal = round($salesTotal * 0.35, 2);
        $cashTotal = round($salesTotal - $cardTotal, 2);

        CashRegisterClosure::create([
            'folio' => self::CLOSURE_PREFIX . strtoupper($branch->slug) . '-' . now()->format('Ymd'),
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'cash_box_number' => '1',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'sales_count' => $sales->count(),
            'sales_total' => $salesTotal,
            'expected_cash' => $cashTotal,
            'card_total' => $cardTotal,
            'other_total' => 0,
            'recharge_total' => 0,
            'expected_drawer_cash' => $cashTotal,
            'counted_cash' => $cashTotal,
            'cash_left' => 200,
            'counted_card' => $cardTotal,
            'cash_difference' => 0,
            'card_difference' => 0,
            'payment_breakdown' => ['efectivo' => $cashTotal, 'tarjeta' => $cardTotal],
            'denomination_breakdown' => ['500' => 0, '200' => 1, '100' => 3, '50' => 4, '20' => 5],
            'notes' => 'Corte demo generado con ventas comerciales para validar reportes.',
            'created_at' => $periodEnd,
            'updated_at' => $periodEnd,
        ]);
    }

    private function seedPurchaseFlow(PurchaseCycle $cycle, Branch $branch, $branchProducts, User $salesUser, User $inventoryUser, int $branchIndex): void
    {
        $createdAt = now()->subDays(5 + $branchIndex)->setTime(9, 0);

        $report = PurchaseReport::create([
            'branch_id' => $branch->id,
            'user_id' => $salesUser->id,
            'folio' => self::PURCHASE_PREFIX . 'LC-' . strtoupper($branch->slug),
            'status' => 'GENERATED',
            'notes' => 'Lista demo para comparar solicitud contra histórico del reporte de ventas.',
            'generated_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $order = PurchaseOrder::create([
            'branch_id' => $branch->id,
            'user_id' => $salesUser->id,
            'assigned_to_user_id' => $inventoryUser->id,
            'folio' => self::PURCHASE_PREFIX . 'OC-' . strtoupper($branch->slug),
            'source' => PurchaseOrder::SOURCE_CENTRAL,
            'status' => PurchaseOrder::STATUS_REVIEW,
            'review_status' => PurchaseOrder::REVIEW_PENDING,
            'estimated_total' => 0,
            'actual_total' => 0,
            'generated_at' => $createdAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        PurchaseCycleBranch::query()
            ->where('purchase_cycle_id', $cycle->id)
            ->where('branch_id', $branch->id)
            ->update([
                'purchase_order_id' => $order->id,
                'submitted_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

        $generalOrder = GeneralPurchaseOrder::create([
            'purchase_cycle_id' => $cycle->id,
            'created_by' => $inventoryUser->id,
            'folio' => self::PURCHASE_PREFIX . 'OG-' . strtoupper($branch->slug),
            'status' => GeneralPurchaseOrder::STATUS_DRAFT,
            'estimated_total' => 0,
            'gross_total' => 0,
            'discount_total' => 0,
            'actual_total' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $estimatedTotal = 0.0;
        foreach ($branchProducts->take(4) as $index => $branchProduct) {
            $product = $branchProduct->product;
            $presentation = $this->purchasePresentationFor($product, $index);
            $unitsPerPackage = $presentation === 'Caja' ? (float) ($product->pieces_per_box ?: 1) : null;
            $packageQuantity = $presentation === 'Caja' ? 1 + (($branchIndex + $index) % 3) : null;
            $requestedQuantity = $presentation === 'Caja'
                ? $packageQuantity * $unitsPerPackage
                : (($product->inventory_unit === 'kg') ? 3.5 + $index : 8 + ($index * 2));
            $estimatedPrice = (float) ($product->cost_per_piece ?? $product->cost ?? 0);
            $lineTotal = round($requestedQuantity * $estimatedPrice, 2);

            PurchaseReportItem::create([
                'purchase_report_id' => $report->id,
                'branch_product_id' => $branchProduct->id,
                'current_stock' => (float) $branchProduct->stock,
                'min_stock' => (float) $branchProduct->min_stock,
                'requested_quantity' => $requestedQuantity,
                'purchase_presentation' => $presentation,
                'package_quantity' => $packageQuantity,
                'units_per_package' => $unitsPerPackage,
                'estimated_price' => $estimatedPrice,
                'estimated_total' => $lineTotal,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'branch_product_id' => $branchProduct->id,
                'product_id' => $product->id,
                'current_stock' => (float) $branchProduct->stock,
                'min_stock' => (float) $branchProduct->min_stock,
                'requested_quantity' => $requestedQuantity,
                'purchase_presentation' => $presentation,
                'package_quantity' => $packageQuantity,
                'units_per_package' => $unitsPerPackage,
                'purchased_quantity' => null,
                'received_quantity' => null,
                'estimated_price' => $estimatedPrice,
                'estimated_total' => $lineTotal,
                'status' => PurchaseOrderItem::STATUS_REQUESTED,
                'receipt_notes' => 'Pendiente de revisión por inventario.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            GeneralPurchaseOrderItem::create([
                'general_purchase_order_id' => $generalOrder->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_description' => $product->description,
                'product_code' => Barcode::query()->where('product_id', $product->id)->orderBy('id')->value('code'),
                'base_unit' => $product->inventory_unit === 'kg' ? 'kg' : 'pza',
                'requested_quantity' => $requestedQuantity,
                'estimated_unit_price' => $estimatedPrice,
                'estimated_total' => $lineTotal,
                'purchase_presentation' => $presentation,
                'package_quantity' => $packageQuantity,
                'units_per_package' => $unitsPerPackage,
                'purchased_quantity' => 0,
                'gross_total' => 0,
                'discount_amount' => 0,
                'actual_total' => 0,
                'net_unit_cost' => 0,
                'purchase_notes' => 'Pendiente de cotización.',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $estimatedTotal += $lineTotal;
        }

        $report->update(['updated_at' => $createdAt]);
        $order->update(['purchase_cycle_id' => $cycle->id, 'general_purchase_order_id' => $generalOrder->id, 'estimated_total' => round($estimatedTotal, 2)]);
        $generalOrder->update(['estimated_total' => round($estimatedTotal, 2)]);
    }

    private function presentationFor(Product $product, int $month, int $day, int $detailIndex): string
    {
        if ($product->inventory_unit === 'kg') {
            return 'kg';
        }

        if ((bool) $product->has_box_presentation && (($month + $day + $detailIndex) % 3 === 0)) {
            return 'box';
        }

        return 'piece';
    }

    private function visualQuantityFor(string $presentation, int $month, int $day, int $detailIndex): float
    {
        if ($presentation === 'kg') {
            return round(0.75 + (($month + $day + $detailIndex) % 5) * 0.25, 3);
        }

        if ($presentation === 'box') {
            return 1 + (($month + $day + $detailIndex) % 2);
        }

        return 1 + (($month * 2 + $day + $detailIndex) % 5);
    }

    private function unitPriceFor(Product $product, string $presentation): float
    {
        if ($presentation === 'box') {
            return (float) ($product->sale_price_per_box ?: ((float) $product->sale_price * max(1, (int) $product->pieces_per_box)));
        }

        return (float) ($product->sale_price_per_piece ?? $product->sale_price ?? 0);
    }

    private function purchasePresentationFor(Product $product, int $index): string
    {
        if ($product->inventory_unit === 'kg') {
            return 'Kilo';
        }

        if ((bool) $product->has_box_presentation && $index % 2 === 0) {
            return 'Caja';
        }

        return 'Pieza';
    }

    private function clearPreviousSeedData(): void
    {
        $saleIds = Sale::query()->where('folio', 'like', self::FOLIO_PREFIX . '%')->pluck('id');
        SaleDetail::query()->whereIn('sale_id', $saleIds)->delete();
        Sale::query()->whereIn('id', $saleIds)->delete();

        CashRegisterClosure::withTrashed()->where('folio', 'like', self::CLOSURE_PREFIX . '%')->forceDelete();

        PurchaseReport::withTrashed()
            ->where('folio', 'like', self::PURCHASE_PREFIX . 'LC-%')
            ->get()
            ->each(function (PurchaseReport $report): void {
                $report->items()->delete();
                $report->forceDelete();
            });

        PurchaseOrder::query()
            ->where('folio', 'like', self::PURCHASE_PREFIX . 'OC-%')
            ->get()
            ->each(function (PurchaseOrder $order): void {
                $order->items()->delete();
                $order->delete();
            });

        GeneralPurchaseOrder::query()
            ->where('folio', 'like', self::PURCHASE_PREFIX . 'OG-%')
            ->get()
            ->each(function (GeneralPurchaseOrder $order): void {
                $order->items()->delete();
                $order->delete();
            });

        PurchaseCycle::query()
            ->where('folio', 'like', self::PURCHASE_PREFIX . 'CICLO-%')
            ->get()
            ->each(function (PurchaseCycle $cycle): void {
                PurchaseCycleBranch::query()->where('purchase_cycle_id', $cycle->id)->delete();
                $cycle->delete();
            });
    }
}
