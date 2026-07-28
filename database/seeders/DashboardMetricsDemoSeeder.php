<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\CashRegisterClosure;
use App\Models\Employee;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos demostrativos, aislados y repetibles para revisar el dashboard.
 * No se invoca desde DatabaseSeeder: se ejecuta solo bajo solicitud explícita.
 */
class DashboardMetricsDemoSeeder extends Seeder
{
    private const FOLIO_PREFIX = 'DEMO-DASH-';

    private const NOTE_PREFIX = 'Demo dashboard: ';

    public function run(): void
    {
        DB::transaction(function () {
            $this->clearPreviousDemo();

            $user = User::query()->firstOrFail();
            $employee = Employee::query()->where('employment_status', 'Activo')->firstOrFail();
            $paymentMethod = PaymentMethod::query()->where('active', true)->firstOrFail();
            $branches = Branch::query()->where('active', true)->orderBy('name')->take(2)->get();

            if ($branches->count() < 2) {
                throw new \RuntimeException('Se requieren al menos dos sucursales activas para el demo del dashboard.');
            }

            $start = now(config('app.timezone'))->subMonthNoOverflow()->startOfMonth();
            $end = $start->copy()->endOfMonth();

            foreach ($branches as $branchIndex => $branch) {
                $products = BranchProduct::query()
                    ->with('product:id,name,cost,sale_price')
                    ->where('branch_id', $branch->id)
                    ->where('status', BranchProduct::STATUS_ACTIVE)
                    ->take(4)
                    ->get();

                if ($products->count() < 2) {
                    throw new \RuntimeException("La sucursal {$branch->name} requiere al menos dos productos activos.");
                }

                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $this->seedSalesAndClosure($branch, $products, $employee, $paymentMethod, $user, $date->copy(), $branchIndex);

                    if ($date->isMonday()) {
                        $this->seedCompletedPurchaseOrder($branch, $products, $user, $date->copy(), $branchIndex);
                    }

                    if ($date->day % 5 === 0) {
                        $this->seedShrinkage($products, $user, $date->copy(), $branchIndex);
                    }
                }
            }
        });
    }

    private function seedSalesAndClosure(Branch $branch, $products, Employee $employee, PaymentMethod $paymentMethod, User $user, Carbon $date, int $branchIndex): void
    {
        $sales = collect();
        foreach ([0, 1] as $saleIndex) {
            $soldProducts = $products->slice($saleIndex, 2)->values();
            $saleDate = $date->copy()->setTime(10 + $saleIndex * 4, 20 + $branchIndex * 10);
            $sale = Sale::create([
                'folio' => self::FOLIO_PREFIX."V-{$branch->id}-".$date->format('Ymd')."-{$saleIndex}",
                'date' => $saleDate,
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'payment_method_id' => $paymentMethod->id,
                'cash_box_number' => '1',
                'total' => 0,
                'cash_received' => 0,
                'change_due' => 0,
                'status' => 'completed',
            ]);

            $total = 0;
            foreach ($soldProducts as $productIndex => $branchProduct) {
                $quantity = 1 + (($date->day + $branchIndex + $saleIndex + $productIndex) % 3);
                $unitPrice = (float) $branchProduct->product->sale_price;
                $subtotal = round($quantity * $unitPrice, 2);
                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $branchProduct->product_id,
                    'quantity' => $quantity,
                    'original_unit_price' => $unitPrice,
                    'discount_percentage' => 0,
                    'discount_amount' => 0,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
                StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'type' => StockMovement::TYPE_OUT,
                    'reason' => StockMovement::REASON_SALE,
                    'quantity' => $quantity,
                    'previous_stock' => 100,
                    'new_stock' => 100 - $quantity,
                    'user_id' => $user->id,
                    'notes' => self::NOTE_PREFIX.'venta confirmada para validar gráficas.',
                    'created_at' => $saleDate,
                    'updated_at' => $saleDate,
                ]);
                $total += $subtotal;
            }
            $sale->update(['total' => $total, 'cash_received' => $total]);
            $sales->push($sale);
        }

        CashRegisterClosure::create([
            'folio' => self::FOLIO_PREFIX."C-{$branch->id}-".$date->format('Ymd'),
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'cash_box_number' => '1',
            'period_start' => $date->copy()->startOfDay()->subSecond(),
            'period_end' => $date->copy()->endOfDay(),
            'sales_count' => $sales->count(),
            'sales_total' => $sales->sum('total'),
            'expected_cash' => $sales->sum('total'),
            'card_total' => 0,
            'other_total' => 0,
            'recharge_total' => 0,
            'expected_drawer_cash' => $sales->sum('total'),
            'counted_cash' => $sales->sum('total'),
            'cash_left' => 0,
            'counted_card' => 0,
            'cash_difference' => 0,
            'card_difference' => 0,
        ]);
    }

    private function seedCompletedPurchaseOrder(Branch $branch, $products, User $user, Carbon $date, int $branchIndex): void
    {
        $order = PurchaseOrder::create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'completed_by' => $user->id,
            'folio' => self::FOLIO_PREFIX."OC-{$branch->id}-".$date->format('Ymd'),
            'source' => PurchaseOrder::SOURCE_CENTRAL,
            'status' => PurchaseOrder::STATUS_COMPLETED,
            'estimated_total' => 0,
            'actual_total' => 0,
            'purchased_at' => $date->toDateString(),
            'generated_at' => $date->copy()->subDay(),
            'completed_at' => $date->copy()->setTime(18, 0),
        ]);

        $total = 0;
        foreach ($products->take(3) as $index => $branchProduct) {
            $quantity = 8 + (($date->weekOfMonth + $index + $branchIndex) % 5);
            $unitCost = (float) $branchProduct->product->cost;
            $itemTotal = round($quantity * $unitCost, 2);
            PurchaseOrderItem::create([
                'purchase_order_id' => $order->id,
                'branch_product_id' => $branchProduct->id,
                'product_id' => $branchProduct->product_id,
                'requested_quantity' => $quantity,
                'purchased_quantity' => $quantity,
                'received_quantity' => $quantity,
                'estimated_price' => $unitCost,
                'estimated_total' => $itemTotal,
                'actual_price' => $unitCost,
                'actual_total' => $itemTotal,
                'status' => PurchaseOrderItem::STATUS_PURCHASED,
                'receipt_notes' => self::NOTE_PREFIX.'recepción confirmada.',
            ]);
            $total += $itemTotal;
        }
        $order->update(['estimated_total' => $total, 'actual_total' => $total]);
    }

    private function seedShrinkage($products, User $user, Carbon $date, int $branchIndex): void
    {
        $branchProduct = $products[($date->day + $branchIndex) % $products->count()];
        $quantity = 1 + (($date->day + $branchIndex) % 2);
        StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'type' => StockMovement::TYPE_OUT,
            'reason' => $date->day % 10 === 0 ? StockMovement::REASON_EXPIRED : StockMovement::REASON_DAMAGED,
            'quantity' => $quantity,
            'previous_stock' => 100,
            'new_stock' => 100 - $quantity,
            'user_id' => $user->id,
            'notes' => self::NOTE_PREFIX.'merma registrada para validar gráficas.',
            'created_at' => $date->copy()->setTime(16, 0),
            'updated_at' => $date->copy()->setTime(16, 0),
        ]);
    }

    private function clearPreviousDemo(): void
    {
        $sales = Sale::query()->where('folio', 'like', self::FOLIO_PREFIX.'V-%')->pluck('id');
        SaleDetail::query()->whereIn('sale_id', $sales)->delete();
        Sale::query()->whereIn('id', $sales)->delete();
        CashRegisterClosure::withTrashed()->where('folio', 'like', self::FOLIO_PREFIX.'C-%')->forceDelete();
        PurchaseOrder::query()->where('folio', 'like', self::FOLIO_PREFIX.'OC-%')->each(function (PurchaseOrder $order) {
            $order->items()->delete();
            $order->delete();
        });
        StockMovement::query()->where('notes', 'like', self::NOTE_PREFIX.'%')->delete();
    }
}
