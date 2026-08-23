<?php

namespace Database\Seeders;

use App\Models\Barcode;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleCancellation;
use App\Models\SaleCancellationDetail;
use App\Models\SaleDetail;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportDemoSeeder extends Seeder
{
    private const FOLIO_PREFIX = 'DEMO-REPVENTAS-';

    private const DAYS_TO_SEED = 18;

    private const SALES_PER_DAY = 5;

    public function run(): void
    {
        $branches = Branch::query()
            ->where('active', true)
            ->orderBy('name')
            ->take(3)
            ->get();
        $employees = Employee::query()
            ->where('employment_status', 'Activo')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->take(8)
            ->get();
        $products = $this->productsForReport();

        if (! $this->hasRequiredCatalogs($branches, $employees, $products)) {
            return;
        }

        DB::transaction(function () use ($branches, $employees, $products): void {
            $this->clearPreviousDemoData();

            $paymentMethods = $this->paymentMethods();
            $customers = Customer::query()
                ->where('active', true)
                ->orderBy('name')
                ->take(10)
                ->get();

            $startDate = CarbonImmutable::create(2026, 8, 5, 0, 0, 0);
            $saleNumber = 1;

            for ($day = 0; $day < self::DAYS_TO_SEED; $day++) {
                $date = $startDate->addDays($day);

                for ($dailySale = 0; $dailySale < self::SALES_PER_DAY; $dailySale++) {
                    $this->createDemoSale(
                        saleNumber: $saleNumber,
                        date: $date->setTime(8 + (($dailySale * 2) % 11), 10 + (($saleNumber * 7) % 45)),
                        branches: $branches,
                        employees: $employees,
                        products: $products,
                        customers: $customers,
                        paymentMethods: $paymentMethods,
                    );

                    $saleNumber++;
                }
            }
        });

        $this->command?->info('Ventas demo creadas para el reporte: '.(self::DAYS_TO_SEED * self::SALES_PER_DAY).' tickets.');
    }

    private function hasRequiredCatalogs(Collection $branches, Collection $employees, Collection $products): bool
    {
        $missing = [];

        if ($branches->isEmpty()) {
            $missing[] = 'sucursales activas';
        }

        if ($employees->isEmpty()) {
            $missing[] = 'empleados activos';
        }

        if ($products->isEmpty()) {
            $missing[] = 'productos activos';
        }

        if ($missing === []) {
            return true;
        }

        $this->command?->warn('No se generaron ventas demo. Faltan: '.implode(', ', $missing).'.');

        return false;
    }

    private function clearPreviousDemoData(): void
    {
        $saleIds = Sale::query()
            ->where('folio', 'like', self::FOLIO_PREFIX.'%')
            ->pluck('id');

        if ($saleIds->isEmpty()) {
            return;
        }

        Sale::query()
            ->whereIn('id', $saleIds)
            ->delete();
    }

    private function productsForReport(): Collection
    {
        $products = Product::query()
            ->with('barcodes:id,product_id,code')
            ->where('active', true)
            ->orderBy('name')
            ->take(40)
            ->get();

        if ($products->count() >= 4) {
            return $products;
        }

        $category = Category::query()
            ->where('active', true)
            ->orderBy('name')
            ->first()
            ?? Category::query()->firstOrCreate(
                ['name' => 'Demo reportes'],
                ['active' => true, 'sort_order' => 999],
            );

        $demoProducts = collect([
            ['name' => 'Demo reporte Coca Cola 600 ml', 'unit' => 'pieza', 'price' => 18.00, 'cost' => 12.50, 'inventory_unit' => 'pza'],
            ['name' => 'Demo reporte Galletas surtidas', 'unit' => 'pieza', 'price' => 15.00, 'cost' => 9.00, 'inventory_unit' => 'pza'],
            ['name' => 'Demo reporte Queso fresco kg', 'unit' => 'kg', 'price' => 92.00, 'cost' => 67.00, 'inventory_unit' => 'kg'],
            ['name' => 'Demo reporte Agua natural 1L', 'unit' => 'pieza', 'price' => 13.00, 'cost' => 7.50, 'inventory_unit' => 'pza'],
            ['name' => 'Demo reporte Papel higienico paquete', 'unit' => 'pieza', 'price' => 42.00, 'cost' => 29.00, 'inventory_unit' => 'pza'],
            ['name' => 'Demo reporte Caja botanas surtidas', 'unit' => 'pieza', 'price' => 11.00, 'cost' => 6.50, 'inventory_unit' => 'pza', 'box_price' => 120.00, 'pieces_per_box' => 12],
        ]);

        $demoProducts->each(function (array $demoProduct, int $index) use ($category): void {
            $product = Product::query()->firstOrCreate(
                ['name' => $demoProduct['name']],
                [
                    'description' => 'Producto demo para visualizar el reporte general de ventas.',
                    'cost' => $demoProduct['cost'],
                    'sale_price' => $demoProduct['price'],
                    'unit' => $demoProduct['unit'],
                    'inventory_unit' => $demoProduct['inventory_unit'],
                    'pieces_per_box' => $demoProduct['pieces_per_box'] ?? null,
                    'has_box_presentation' => isset($demoProduct['box_price']),
                    'inventory_quantity_mode' => 'base',
                    'cost_per_piece' => $demoProduct['cost'],
                    'sale_price_per_piece' => $demoProduct['price'],
                    'cost_per_box' => isset($demoProduct['box_price']) ? $demoProduct['cost'] * ($demoProduct['pieces_per_box'] ?? 1) : null,
                    'sale_price_per_box' => $demoProduct['box_price'] ?? null,
                    'category_id' => $category->id,
                    'active' => true,
                ],
            );

            Barcode::query()->firstOrCreate(
                ['code' => 'DEMOREP'.str_pad((string) ($index + 1), 6, '0', STR_PAD_LEFT)],
                ['product_id' => $product->id, 'type' => 'demo', 'base_quantity' => 1, 'active' => true],
            );
        });

        return Product::query()
            ->with('barcodes:id,product_id,code')
            ->where('active', true)
            ->orderBy('name')
            ->take(40)
            ->get();
    }

    private function paymentMethods(): Collection
    {
        $cash = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Efectivo'],
            ['active' => true],
        );
        $card = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Tarjeta'],
            ['active' => true],
        );
        $credit = PaymentMethod::query()->firstOrCreate(
            ['name' => 'Credito empleado'],
            ['active' => true],
        );

        return collect([$cash, $card, $credit])
            ->filter(fn (PaymentMethod $method) => $method->active)
            ->values();
    }

    private function createDemoSale(
        int $saleNumber,
        CarbonImmutable $date,
        Collection $branches,
        Collection $employees,
        Collection $products,
        Collection $customers,
        Collection $paymentMethods,
    ): void {
        $branch = $branches[($saleNumber - 1) % $branches->count()];
        $employee = $employees[($saleNumber + 1) % $employees->count()];
        $paymentMethod = $paymentMethods[($saleNumber + 2) % $paymentMethods->count()];
        $customer = $customers->isEmpty() ? null : $customers[($saleNumber + 3) % $customers->count()];
        $isCancelled = $saleNumber % 13 === 0;

        $sale = Sale::create([
            'folio' => self::FOLIO_PREFIX.$date->format('Ymd').'-'.str_pad((string) $saleNumber, 4, '0', STR_PAD_LEFT),
            'date' => $date,
            'employee_id' => $employee->id,
            'customer_id' => $customer?->id,
            'branch_id' => $branch->id,
            'cash_box_number' => (string) (1 + ($saleNumber % 2)),
            'payment_method_id' => $paymentMethod->id,
            'total' => 0,
            'cash_received' => 0,
            'change_due' => 0,
            'status' => $isCancelled ? 'cancelled' : 'completed',
            'cancelled_at' => $isCancelled ? $date->addHours(2) : null,
            'created_at' => $date,
            'updated_at' => $date,
        ]);

        $total = $this->createSaleDetails($sale, $products, $saleNumber, $date);
        $cashReceived = $paymentMethod->name === 'Efectivo'
            ? ceil(($total + 20) / 10) * 10
            : $total;

        $sale->update([
            'total' => $total,
            'cash_received' => $cashReceived,
            'change_due' => max(0, $cashReceived - $total),
        ]);

        if ($isCancelled) {
            $this->createCancellation($sale, $date, $paymentMethod);
        }
    }

    private function createSaleDetails(Sale $sale, Collection $products, int $saleNumber, CarbonImmutable $date): float
    {
        $detailsCount = 2 + ($saleNumber % 4);
        $total = 0.0;

        for ($index = 0; $index < $detailsCount; $index++) {
            $product = $products[($saleNumber + ($index * 5)) % $products->count()];
            $barcode = $product->barcodes->first()
                ?? Barcode::query()->where('product_id', $product->id)->orderBy('id')->first();
            $presentation = $this->presentationFor($product, $saleNumber, $index);
            $quantity = $this->quantityFor($product, $presentation, $saleNumber, $index);
            $piecesPerBox = (int) ($product->getAttribute('pieces_per_box') ?: 0);
            $baseQuantity = $presentation === 'box'
                ? $quantity * max(1, $piecesPerBox)
                : $quantity;
            $unitPrice = $this->unitPriceFor($product, $presentation);
            $discountPercentage = (($saleNumber + $index) % 9 === 0) ? 5.00 : 0.00;
            $discountAmount = round(($unitPrice * $quantity) * ($discountPercentage / 100), 2);
            $subtotal = round(($unitPrice * $quantity) - $discountAmount, 2);

            SaleDetail::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'barcode_id' => $barcode?->id,
                'quantity' => $quantity,
                'sale_unit' => $presentation,
                'base_quantity' => $baseQuantity,
                'pieces_per_box' => $presentation === 'box' ? $piecesPerBox : null,
                'original_unit_price' => $unitPrice,
                'discount_percentage' => $discountPercentage,
                'discount_amount' => $discountAmount,
                'unit_price' => $unitPrice,
                'unit_cost' => (float) ($product->getAttribute('cost_per_piece') ?: $product->cost ?: 0),
                'subtotal' => $subtotal,
                'created_at' => $date,
                'updated_at' => $date,
            ]);

            $total += $subtotal;
        }

        return round($total, 2);
    }

    private function createCancellation(Sale $sale, CarbonImmutable $date, PaymentMethod $paymentMethod): void
    {
        $cancellation = SaleCancellation::create([
            'sale_id' => $sale->id,
            'branch_id' => $sale->branch_id,
            'payment_method_id' => $paymentMethod->id,
            'cancelled_by_user_id' => null,
            'cash_box_number' => $sale->cash_box_number ?: '1',
            'amount' => $sale->total,
            'reason' => 'Registro demo para validar el reporte de ventas.',
            'cancelled_at' => $date->addHours(2),
            'created_at' => $date->addHours(2),
            'updated_at' => $date->addHours(2),
        ]);

        $sale->details()->get()->each(function (SaleDetail $detail) use ($cancellation, $date): void {
            SaleCancellationDetail::create([
                'sale_cancellation_id' => $cancellation->id,
                'sale_detail_id' => $detail->id,
                'branch_product_id' => null,
                'product_id' => $detail->product_id,
                'barcode_id' => $detail->barcode_id,
                'return_stock_movement_id' => null,
                'quantity' => $detail->quantity,
                'sale_unit' => $detail->sale_unit,
                'base_quantity' => $detail->base_quantity ?: $detail->quantity,
                'pieces_per_box' => $detail->pieces_per_box,
                'unit_price' => $detail->unit_price,
                'subtotal' => $detail->subtotal,
                'created_at' => $date->addHours(2),
                'updated_at' => $date->addHours(2),
            ]);
        });
    }

    private function presentationFor(Product $product, int $saleNumber, int $index): string
    {
        if ((bool) $product->getAttribute('has_box_presentation') && ($saleNumber + $index) % 5 === 0) {
            return 'box';
        }

        if ($product->getAttribute('inventory_unit') === 'kg' && ($saleNumber + $index) % 3 === 0) {
            return 'kg';
        }

        return 'piece';
    }

    private function quantityFor(Product $product, string $presentation, int $saleNumber, int $index): float
    {
        if ($presentation === 'kg' || $product->getAttribute('inventory_unit') === 'kg') {
            return round(0.250 + ((($saleNumber + $index) % 7) * 0.125), 3);
        }

        if ($presentation === 'box') {
            return 1 + (($saleNumber + $index) % 2);
        }

        return 1 + (($saleNumber + $index) % 4);
    }

    private function unitPriceFor(Product $product, string $presentation): float
    {
        $price = match ($presentation) {
            'box' => $product->getAttribute('sale_price_per_box'),
            'piece' => $product->getAttribute('sale_price_per_piece'),
            default => null,
        };

        return (float) ($price ?: $product->sale_price ?: 1);
    }
}
