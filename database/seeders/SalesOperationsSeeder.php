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
use App\Models\ProductBatch;
use App\Models\PurchaseCycle;
use App\Models\PurchaseCycleBranch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReport;
use App\Models\PurchaseReportItem;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SalesOperationsSeeder extends Seeder
{
    private const FOLIO_PREFIX = 'SEED-VENTAS-';

    private const PURCHASE_PREFIX = 'SEED-COMPRA-';

    private const CLOSURE_PREFIX = 'SEED-CORTE-';

    private const HISTORY_MONTHS = 6;

    private const SALES_PER_DAY = 1;

    private const PRODUCTS_PER_SALE = 4;

    private const PURCHASE_PRODUCTS_PER_BRANCH = 24;

    public function run(): void
    {
        // Seeder intencionalmente vacío para pruebas limpias en servidor.
        return;

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
                $this->ensureBranchCatalogForSales($branch, $inventoryUser, $branchIndex);

                $branchProducts = BranchProduct::query()
                    ->with('product:id,name,description,cost,sale_price,inventory_unit,pieces_per_box,has_box_presentation,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box')
                    ->where('branch_id', $branch->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->whereHas('product', fn ($query) => $query->where('active', true))
                    ->orderBy('id')
                    ->get();

                if ($branchProducts->isEmpty()) {
                    continue;
                }

                $this->seedCommercialSales($branch, $branchProducts, $seller, $salesUser, $customers, $paymentMethods, $branchIndex);
                $this->normalizeCurrentStockForSalesReport($branch, $inventoryUser, $branchIndex);
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

    private function ensureBranchCatalogForSales(Branch $branch, User $inventoryUser, int $branchIndex): void
    {
        Product::query()
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->each(function (Product $product, int $index) use ($branch, $inventoryUser, $branchIndex): void {
                $branchProduct = BranchProduct::withTrashed()
                    ->where('branch_id', $branch->id)
                    ->where('product_id', $product->id)
                    ->first();

                if ($branchProduct) {
                    if ($branchProduct->trashed()) {
                        $branchProduct->restore();
                    }

                    if ($branchProduct->status !== BranchProduct::STATUS_ACTIVE) {
                        $branchProduct->update(['status' => BranchProduct::STATUS_ACTIVE]);
                    }

                    return;
                }

                $stock = $this->initialSalesStockFor($product, $index, $branchIndex);
                $minStock = $product->inventory_unit === 'kg'
                    ? 3.000
                    : 8.000;

                $branchProduct = BranchProduct::create([
                    'branch_id' => $branch->id,
                    'product_id' => $product->id,
                    'barcode' => Barcode::query()->where('product_id', $product->id)->orderBy('id')->value('code'),
                    'stock' => $stock,
                    'min_stock' => $minStock,
                    'status' => BranchProduct::STATUS_ACTIVE,
                    'tracks_batches' => false,
                    'tracks_expiration' => false,
                    'last_restocked_at' => now()->subMonthsNoOverflow(self::HISTORY_MONTHS)->toDateString(),
                ]);

                StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'type' => StockMovement::TYPE_IN,
                    'reason' => StockMovement::REASON_PURCHASE,
                    'quantity' => $stock,
                    'unit_cost' => (float) ($product->cost_per_piece ?? $product->cost ?? 0),
                    'previous_stock' => 0,
                    'new_stock' => $stock,
                    'user_id' => $inventoryUser->id,
                    'notes' => 'Seeder ventas historicas: inventario base para venta diaria.',
                    'created_at' => now()->subMonthsNoOverflow(self::HISTORY_MONTHS),
                    'updated_at' => now()->subMonthsNoOverflow(self::HISTORY_MONTHS),
                ]);
            });
    }

    private function initialSalesStockFor(Product $product, int $index, int $branchIndex): float
    {
        if ($product->inventory_unit === 'kg') {
            return round(45 + (($index + $branchIndex) % 35) + ($branchIndex * 2.5), 3);
        }

        return 90 + (($index * 7 + $branchIndex * 11) % 85);
    }

    private function seedCommercialSales(Branch $branch, $branchProducts, Employee $seller, User $salesUser, $customers, $paymentMethods, int $branchIndex): void
    {
        $start = now()->startOfDay()->subMonthsNoOverflow(self::HISTORY_MONTHS);
        $end = now()->startOfDay();
        $saleNumber = 1;

        for ($dateCursor = $start->copy(); $dateCursor->lte($end); $dateCursor->addDay()) {
            for ($dailySale = 0; $dailySale < self::SALES_PER_DAY; $dailySale++) {
                $date = $dateCursor->copy()
                    ->setTime(
                        8 + (($saleNumber + $branchIndex + $dailySale) % 12),
                        10 + (($saleNumber * 7 + $branchIndex * 5) % 45),
                    );

                $sale = Sale::create([
                    'folio' => self::FOLIO_PREFIX . $branch->slug . '-' . $date->format('Ymd') . '-' . str_pad((string) $saleNumber, 4, '0', STR_PAD_LEFT),
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
                $saleProducts = $this->saleProductsForDate($branchProducts, (($saleNumber - 1) * self::PRODUCTS_PER_SALE) + $branchIndex);

                foreach ($saleProducts as $detailIndex => $branchProduct) {
                    $branchProduct = $branchProduct->fresh(['product']);
                    $product = $branchProduct->product;
                    $presentation = $this->presentationFor($product, $date, $detailIndex);
                    $quantity = $this->visualQuantityFor($presentation, $date, $detailIndex);
                    $piecesPerBox = (int) ($product->pieces_per_box ?: 0);
                    $baseQuantity = $presentation === 'box'
                        ? $quantity * max(1, $piecesPerBox)
                        : $quantity;

                    if ((float) $branchProduct->stock < $baseQuantity) {
                        [$presentation, $quantity, $baseQuantity] = $this->fitSaleQuantityToStock($product, $branchProduct, $presentation, $quantity, $baseQuantity);
                    }

                    if ($baseQuantity <= 0) {
                        continue;
                    }

                    $unitPrice = $this->unitPriceFor($product, $presentation);
                    $originalUnitPrice = $unitPrice;
                    $discountPercentage = (($saleNumber + $detailIndex) % 7 === 0) ? 5.00 : 0.00;
                    $discountAmount = round(($originalUnitPrice * $quantity) * ($discountPercentage / 100), 2);
                    $subtotal = round(($unitPrice * $quantity) - $discountAmount, 2);

                    $this->recordSaleStockMovement(
                        branchProduct: $branchProduct,
                        product: $product,
                        presentation: $presentation,
                        quantity: $baseQuantity,
                        userId: $salesUser->id,
                        date: $date,
                    );

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

                if ($total <= 0) {
                    $sale->delete();
                    continue;
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
        $purchaseProducts = $this->rotatingBranchProducts(
            branchProducts: $branchProducts,
            offset: $branchIndex * self::PURCHASE_PRODUCTS_PER_BRANCH,
            limit: self::PURCHASE_PRODUCTS_PER_BRANCH,
        );

        foreach ($purchaseProducts as $index => $branchProduct) {
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

    private function presentationFor(Product $product, Carbon $date, int $detailIndex): string
    {
        if ($product->inventory_unit === 'kg') {
            return 'kg';
        }

        if ((bool) $product->has_box_presentation && (($date->day + $detailIndex) % 11 === 0)) {
            return 'box';
        }

        return 'piece';
    }

    private function visualQuantityFor(string $presentation, Carbon $date, int $detailIndex): float
    {
        if ($presentation === 'kg') {
            return round(0.25 + (($date->day + $detailIndex) % 4) * 0.125, 3);
        }

        if ($presentation === 'box') {
            return 1;
        }

        return 1 + (($date->day + $detailIndex) % 3);
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

    private function rotatingBranchProducts($branchProducts, int $offset, int $limit)
    {
        $count = $branchProducts->count();

        if ($count === 0) {
            return collect();
        }

        return collect(range(0, min($limit, $count) - 1))
            ->map(fn (int $index) => $branchProducts[($offset + $index) % $count])
            ->values();
    }

    private function saleProductsForDate($branchProducts, int $offset)
    {
        $count = $branchProducts->count();

        if ($count === 0) {
            return collect();
        }

        $selected = collect();

        for ($index = 0; $index < $count && $selected->count() < self::PRODUCTS_PER_SALE; $index++) {
            $branchProduct = $branchProducts[($offset + $index) % $count]->fresh(['product']);

            if (! $branchProduct || ! $branchProduct->product || (float) $branchProduct->stock <= 0) {
                continue;
            }

            $selected->push($branchProduct);
        }

        return $selected;
    }

    private function fitSaleQuantityToStock(Product $product, BranchProduct $branchProduct, string $presentation, float $quantity, float $baseQuantity): array
    {
        $availableStock = (float) $branchProduct->stock;

        if ($availableStock <= 0) {
            return [$presentation, 0, 0];
        }

        if ($product->inventory_unit === 'kg') {
            $quantity = min($quantity, $availableStock);

            return ['kg', round($quantity, 3), round($quantity, 3)];
        }

        if ($presentation === 'box') {
            $piecesPerBox = max(1, (int) $product->pieces_per_box);

            if ($availableStock >= $piecesPerBox) {
                return ['box', 1, $piecesPerBox];
            }
        }

        $pieces = floor($availableStock);

        return ['piece', $pieces, $pieces];
    }

    private function recordSaleStockMovement(BranchProduct $branchProduct, Product $product, string $presentation, float $quantity, ?int $userId, Carbon $date): void
    {
        $branchProduct = BranchProduct::whereKey($branchProduct->id)->lockForUpdate()->firstOrFail();
        $previousStock = (float) $branchProduct->stock;
        $newStock = max(0, $previousStock - $quantity);

        $movement = StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'type' => StockMovement::TYPE_OUT,
            'reason' => StockMovement::REASON_SALE,
            'quantity' => $quantity,
            'unit_cost' => $presentation === 'box'
                ? (float) ($product->cost_per_box ?? $product->cost_per_piece ?? $product->cost ?? 0)
                : (float) ($product->cost_per_piece ?? $product->cost ?? 0),
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'user_id' => $userId,
            'notes' => 'Seeder ventas historicas: venta generada desde punto de venta',
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $this->consumeBatchesForSeedSale($movement, $branchProduct, $quantity, $date);

        $branchProduct->update([
            'stock' => (float) ProductBatch::query()
                ->where('branch_product_id', $branchProduct->id)
                ->whereIn('status', [
                    ProductBatch::STATUS_ACTIVE,
                    ProductBatch::STATUS_SEASONAL,
                ])
                ->sum('quantity') ?: $newStock,
        ]);
    }

    private function consumeBatchesForSeedSale(StockMovement $movement, BranchProduct $branchProduct, float $quantity, Carbon $date): void
    {
        $remaining = $quantity;

        ProductBatch::query()
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
            ->each(function (ProductBatch $batch) use (&$remaining, $movement, $date): void {
                if ($remaining <= 0) {
                    return;
                }

                $previousBatchQuantity = (float) $batch->quantity;
                $take = min($previousBatchQuantity, $remaining);

                if ($take <= 0) {
                    return;
                }

                $newBatchQuantity = $previousBatchQuantity - $take;

                $batch->update([
                    'quantity' => $newBatchQuantity,
                    'updated_at' => $date,
                ]);

                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'product_batch_id' => $batch->id,
                    'quantity' => $take,
                    'previous_batch_quantity' => $previousBatchQuantity,
                    'new_batch_quantity' => $newBatchQuantity,
                    'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);

                $remaining -= $take;
            });
    }

    private function normalizeCurrentStockForSalesReport(Branch $branch, User $inventoryUser, int $branchIndex): void
    {
        $periodStart = now()->startOfDay()->subMonthsNoOverflow(self::HISTORY_MONTHS);
        $periodEnd = now()->startOfDay();
        $months = max(1, self::HISTORY_MONTHS);

        BranchProduct::query()
            ->with('product:id,inventory_unit,cost,cost_per_piece')
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->orderBy('id')
            ->get()
            ->each(function (BranchProduct $branchProduct, int $index) use ($periodStart, $periodEnd, $months, $inventoryUser, $branchIndex): void {
                $sold = (float) SaleDetail::query()
                    ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                    ->where('sales.branch_id', $branchProduct->branch_id)
                    ->where('sales.status', 'completed')
                    ->where('sales.folio', 'like', self::FOLIO_PREFIX . '%')
                    ->whereDate('sales.date', '>=', $periodStart->toDateString())
                    ->whereDate('sales.date', '<=', $periodEnd->toDateString())
                    ->where('sale_details.product_id', $branchProduct->product_id)
                    ->sum(DB::raw('COALESCE(sale_details.base_quantity, sale_details.quantity)'));

                if ($sold <= 0) {
                    return;
                }

                $monthlyAverage = $sold / $months;
                $targetStock = $this->targetReportStockFor($branchProduct, $monthlyAverage, $index, $branchIndex);
                $previousStock = (float) $branchProduct->stock;

                if (round($previousStock, 3) === round($targetStock, 3)) {
                    return;
                }

                $branchProduct->update([
                    'stock' => $targetStock,
                ]);

                StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                    'reason' => StockMovement::REASON_INVENTORY_DIFFERENCE,
                    'quantity' => round($targetStock - $previousStock, 3),
                    'unit_cost' => (float) ($branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0),
                    'previous_stock' => $previousStock,
                    'new_stock' => $targetStock,
                    'user_id' => $inventoryUser->id,
                    'notes' => 'Seeder ventas historicas: ajuste de stock para reporte de reposicion.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    private function targetReportStockFor(BranchProduct $branchProduct, float $monthlyAverage, int $index, int $branchIndex): float
    {
        $unit = $branchProduct->product?->inventory_unit === 'kg' ? 'kg' : 'pza';
        $scenario = ($branchProduct->product_id + $branchIndex) % 4;

        $stock = match ($scenario) {
            0 => max(0, $monthlyAverage - (1 + ($index % 3))),
            1 => $monthlyAverage + 4 + ($index % 6),
            2 => $branchIndex % 2 === 0
                ? max(0, $monthlyAverage - 1)
                : $monthlyAverage + 2,
            default => $monthlyAverage,
        };

        return $unit === 'kg'
            ? round($stock, 3)
            : (float) max(0, (int) round($stock));
    }

    private function clearPreviousSeedData(): void
    {
        $seedCycleIds = PurchaseCycle::query()
            ->where('folio', 'like', self::PURCHASE_PREFIX . 'CICLO-%')
            ->pluck('id');

        $saleIds = Sale::query()->where('folio', 'like', self::FOLIO_PREFIX . '%')->pluck('id');
        $movementIds = StockMovement::query()
            ->whereIn('type', [
                StockMovement::TYPE_OUT,
                StockMovement::TYPE_ADJUSTMENT,
            ])
            ->where(function ($query) {
                $query
                    ->where('notes', 'like', 'Seeder ventas historicas:%')
                    ->orWhere('notes', 'like', 'Seeder ventas historicas: ajuste de stock%');
            })
            ->pluck('id');

        $this->restoreSeedSaleMovements($movementIds);

        StockMovementBatch::query()->whereIn('stock_movement_id', $movementIds)->delete();
        StockMovement::query()->whereIn('id', $movementIds)->delete();
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
            ->where(function ($query) use ($seedCycleIds) {
                $query
                    ->where('folio', 'like', self::PURCHASE_PREFIX . 'OC-%')
                    ->when($seedCycleIds->isNotEmpty(), fn ($subQuery) => $subQuery->orWhereIn('purchase_cycle_id', $seedCycleIds));
            })
            ->get()
            ->each(function (PurchaseOrder $order): void {
                $order->items()->delete();
                $order->delete();
            });

        GeneralPurchaseOrder::query()
            ->where(function ($query) use ($seedCycleIds) {
                $query
                    ->where('folio', 'like', self::PURCHASE_PREFIX . 'OG-%')
                    ->when($seedCycleIds->isNotEmpty(), fn ($subQuery) => $subQuery->orWhereIn('purchase_cycle_id', $seedCycleIds));
            })
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

    private function restoreSeedSaleMovements($movementIds): void
    {
        if ($movementIds->isEmpty()) {
            return;
        }

        StockMovementBatch::query()
            ->whereIn('stock_movement_id', $movementIds)
            ->orderByDesc('id')
            ->get()
            ->each(function (StockMovementBatch $movementBatch): void {
                ProductBatch::whereKey($movementBatch->product_batch_id)->update([
                    'quantity' => $movementBatch->previous_batch_quantity,
                ]);
            });

        StockMovement::query()
            ->whereIn('id', $movementIds)
            ->orderByDesc('id')
            ->get()
            ->each(function (StockMovement $movement): void {
                BranchProduct::whereKey($movement->branch_product_id)->update([
                    'stock' => $movement->previous_stock,
                ]);
            });
    }
}
