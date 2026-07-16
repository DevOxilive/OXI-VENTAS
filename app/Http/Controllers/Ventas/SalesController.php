<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\PaymentMethod;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\TicketTemplate;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->loadMissing(['branches', 'role']);
        $allowedBranches = $this->allowedBranches($user);
        $selectorMode = $this->shouldShowBranchSelector($request, $user, $allowedBranches);
        $branch = $selectorMode
            ? null
            : $this->resolveBranch($request, $user, $allowedBranches);

        if (!$selectorMode && !$branch) {
            throw new AuthorizationException('No tienes una sucursal disponible para generar ventas.');
        }

        $products = $selectorMode
            ? collect()
            : BranchProduct::query()
                ->with([
                    'product.category',
                    'product.barcodes:id,product_id,code',
                    'branch:id,name,slug',
                    'batches' => fn ($query) => $query
                        ->whereIn('status', [
                            ProductBatch::STATUS_ACTIVE,
                            ProductBatch::STATUS_SEASONAL,
                        ])
                        ->where('quantity', '>', 0)
                        ->whereNotNull('expiration_date')
                        ->whereDate('expiration_date', '>=', today())
                        ->whereDate('expiration_date', '<=', now()->addDays(20))
                        ->orderBy('expiration_date')
                        ->orderBy('received_at')
                        ->orderBy('id'),
                ])
                ->where('branch_id', $branch->id)
                ->where('status', BranchProduct::STATUS_ACTIVE)
                ->whereHas('product', fn ($query) => $query->where('active', true))
                ->orderBy('id')
                ->get();

        $mappedProducts = $products instanceof Collection
            ? $products->map(fn ($branchProduct) => $this->mapBranchProduct($branchProduct))
                ->values()
            : collect();

        $ticketTemplate = TicketTemplate::salesTemplate();

        $paymentMethods = $this->allowedPaymentMethods();

        return Inertia::render('Ventas/Home', [
            'selectorMode' => $selectorMode,
            'currentBranch' => $branch ? [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'color' => $branch->color,
            ] : null,
            'branchesDB' => $allowedBranches,
            'productsDB' => $mappedProducts,
            'paymentMethodsDB' => $paymentMethods,
            'defaultPaymentMethodId' => $this->defaultPaymentMethodId($paymentMethods),
            'nearExpirationAlerts' => $selectorMode
                ? []
                : $this->buildNearExpirationAlerts($products)->take(12)->values(),
            'ticketTemplate' => [
                'id' => $ticketTemplate->id,
                'name' => $ticketTemplate->name,
                'slug' => $ticketTemplate->slug,
                'settings' => TicketTemplate::sanitizeSettings($ticketTemplate->settings ?? []),
            ],
        ]);
    }

    public function store(Request $request, StockMovementService $stockService)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'cash_box_number' => ['nullable', 'string', 'max:10'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'cash_received' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.barcode_id' => ['nullable', 'exists:barcodes,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.original_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user()->loadMissing(['branches', 'role']);
        $branch = $this->resolveBranchById($data['branch_id'], $user);
        $paymentMethod = $this->allowedPaymentMethods()
            ->firstWhere('id', (int) $data['payment_method_id']);

        if (!$paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'La forma de pago debe ser efectivo o pago con tarjeta.',
            ]);
        }

        if (!$user->employee_id) {
            throw ValidationException::withMessages([
                'employee_id' => 'Tu usuario no tiene un empleado asociado para registrar la venta.',
            ]);
        }

        $sale = DB::transaction(function () use ($data, $user, $branch, $paymentMethod, $stockService) {
            $sale = Sale::create([
                'date' => now(),
                'employee_id' => $user->employee_id,
                'customer_id' => null,
                'branch_id' => $branch->id,
                'cash_box_number' => (string) ($data['cash_box_number'] ?? '1'),
                'payment_method_id' => $data['payment_method_id'],
                'total' => 0,
                'cash_received' => 0,
                'change_due' => 0,
                'status' => 'completed',
            ]);

            $total = 0;

            foreach ($data['items'] as $item) {
                $branchProduct = BranchProduct::with([
                    'product',
                    'product.barcodes:id,product_id,code',
                ])
                    ->whereKey($item['branch_product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ((int) $branchProduct->branch_id !== (int) $branch->id) {
                    throw ValidationException::withMessages([
                        'items' => 'Uno de los productos no pertenece a la sucursal seleccionada.',
                    ]);
                }

                $quantity = (float) $item['quantity'];
                $originalUnitPrice = isset($item['original_unit_price'])
                    ? (float) $item['original_unit_price']
                    : (float) $item['unit_price'];
                $discountPercentage = round((float) ($item['discount_percentage'] ?? 0), 2);
                $discountAmount = round((float) ($item['discount_amount'] ?? 0), 2);
                $unitPrice = round((float) $item['unit_price'], 2);
                $availableStock = (float) $branchProduct->stock;

                if ($discountPercentage < 0 || $discountPercentage > 100) {
                    throw ValidationException::withMessages([
                        'items' => 'El descuento debe estar entre 0% y 100%.',
                    ]);
                }

                if ($unitPrice > $originalUnitPrice) {
                    throw ValidationException::withMessages([
                        'items' => 'El precio final no puede ser mayor al precio original del producto.',
                    ]);
                }

                if ($availableStock < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => sprintf(
                            'No hay stock suficiente para %s. Disponible: %s',
                            $branchProduct->product?->name ?? 'el producto',
                            number_format($availableStock, 2)
                        ),
                    ]);
                }

                $useBatches = (bool) $branchProduct->tracks_batches
                    && ProductBatch::where('branch_product_id', $branchProduct->id)
                        ->whereIn('status', [
                            ProductBatch::STATUS_ACTIVE,
                            ProductBatch::STATUS_SEASONAL,
                        ])
                        ->where('quantity', '>', 0)
                        ->exists();

                if ($useBatches) {
                    $manualBatches = $this->allocateBatchesForSale($branchProduct, $quantity);

                    $stockService->move(
                        branchProduct: $branchProduct,
                        type: StockMovement::TYPE_OUT,
                        reason: StockMovement::REASON_SALE,
                        quantity: $quantity,
                        notes: 'Venta generada desde punto de venta',
                        userId: $user->id,
                        batches: [],
                        batchAllocationMethod: StockMovementBatch::ALLOCATION_MANUAL,
                        manualBatches: $manualBatches
                    );
                } else {
                    $previousStock = (float) $branchProduct->stock;
                    $newStock = $previousStock - $quantity;

                    $branchProduct->update([
                        'stock' => $newStock,
                    ]);

                    StockMovement::create([
                        'branch_product_id' => $branchProduct->id,
                        'type' => StockMovement::TYPE_OUT,
                        'reason' => StockMovement::REASON_SALE,
                        'quantity' => $quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'user_id' => $user->id,
                        'notes' => 'Venta generada desde punto de venta',
                    ]);
                }

                $subtotal = round($quantity * $unitPrice, 2);
                $total += $subtotal;

                SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $branchProduct->product_id,
                    'barcode_id' => $item['barcode_id'] ?? null,
                    'lot_id' => null,
                    'quantity' => $quantity,
                    'original_unit_price' => $originalUnitPrice,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $total = round($total, 2);
            $isCashPayment = $this->isCashPaymentMethod($paymentMethod->name);
            $cashReceived = $isCashPayment
                ? round((float) $data['cash_received'], 2)
                : $total;

            if ($isCashPayment && $cashReceived < $total) {
                throw ValidationException::withMessages([
                    'cash_received' => 'El efectivo recibido no puede ser menor al total de la venta.',
                ]);
            }

            $sale->update([
                'folio' => 'V-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                'total' => $total,
                'cash_received' => $cashReceived,
                'change_due' => $isCashPayment ? round($cashReceived - $total, 2) : 0,
            ]);

            return $sale;
        });

        $expirationAlerts = $this->buildRemainingNearExpirationAlertsAfterSale($sale);

        return back()->with([
            'success' => 'Venta registrada correctamente.',
            'sale_folio' => $sale->folio,
            'print_job' => $this->buildPrintJobPayload($sale),
            'expiration_alerts' => $expirationAlerts,
        ]);
    }

    private function mapBranchProduct(BranchProduct $branchProduct): array
    {
        $product = $branchProduct->product;
        $primaryBarcode = $product?->barcodes?->first();

        return [
            'branch_product_id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'barcode_id' => $primaryBarcode?->id,
            'barcode' => $branchProduct->barcode ?? $primaryBarcode?->code ?? '',
            'barcodes' => $product?->barcodes?->pluck('code')->values() ?? [],
            'name' => $product?->name ?? 'Producto sin nombre',
            'category' => $product?->category?->name ?? '',
            'image' => $product?->image
                ? route('inventory.products.image', ['product' => $product->id])
                : null,
            'price' => (float) ($product?->sale_price ?? 0),
            'cost' => (float) ($product?->cost ?? 0),
            'margin_percentage' => (float) ($product?->margin_percentage ?? 0),
            'stock' => (float) ($branchProduct->stock ?? 0),
            'tracks_batches' => (bool) $branchProduct->tracks_batches,
            'near_expiration_alert' => $this->mapNearExpirationBatch($branchProduct),
            'searchable' => mb_strtolower(implode(' ', array_filter([
                $product?->name,
                $branchProduct->barcode,
                $primaryBarcode?->code,
                $product?->category?->name,
            ]))),
        ];
    }

    private function allowedBranches($user)
    {
        return $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('name')
            ->get();
    }

    private function shouldShowBranchSelector(Request $request, $user, $allowedBranches): bool
    {
        return $user->role?->name === 'Administrador'
            && !$request->filled('branch')
            && $allowedBranches->isNotEmpty();
    }

    private function resolveBranch(Request $request, $user, $allowedBranches): ?Branch
    {
        $branchId = $request->query('branch');

        if (!$branchId && $allowedBranches->count() === 1) {
            $branchId = $allowedBranches->first()->id;
        }

        if (!$branchId && $allowedBranches->isNotEmpty()) {
            $branchId = $allowedBranches->first()->id;
        }

        if (!$branchId) {
            return null;
        }

        return $this->resolveBranchById($branchId, $user);
    }

    private function resolveBranchById($branchId, $user): Branch
    {
        $query = Branch::query()->whereKey($branchId)->where('active', true);

        if ($user->role?->name !== 'Administrador') {
            $query->whereIn('id', $user->accessibleBranchIds());
        }

        return $query->firstOrFail();
    }

    private function allocateBatchesForSale(BranchProduct $branchProduct, float $quantity): array
    {
        $remaining = $quantity;

        $allocation = ProductBatch::query()
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
            ->reduce(function (array $allocation, ProductBatch $batch) use (&$remaining) {
                if ($remaining <= 0) {
                    return $allocation;
                }

                $available = (float) $batch->quantity;
                $take = min($available, $remaining);

                if ($take <= 0) {
                    return $allocation;
                }

                $allocation[] = [
                    'id' => $batch->id,
                    'quantity' => $take,
                ];

                $remaining -= $take;

                return $allocation;
            }, []);

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'items' => 'No hay stock suficiente en lotes para completar la venta.',
            ]);
        }

        return $allocation;
    }

    private function mapNearExpirationBatch(BranchProduct $branchProduct): ?array
    {
        $batch = $branchProduct->batches->first();

        if (!$batch) {
            return null;
        }

        return [
            'lot_number' => $batch->lot_number,
            'quantity' => (float) $batch->quantity,
            'expiration_date' => optional($batch->expiration_date)?->toDateString(),
            'formatted_expiration_date' => $batch->formatted_expiration_date,
            'days_to_expire' => $batch->days_to_expire,
            'message' => $batch->expiration_human_text,
        ];
    }

    private function allowedPaymentMethods(): Collection
    {
        return PaymentMethod::query()
            ->where('active', true)
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(name) LIKE ?', ['%efectivo%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%cash%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%tarjeta%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%card%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%credito%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%debito%']);
            })
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(function (PaymentMethod $method) {
                $method->name = $this->displayPaymentMethodName($method->name);

                return $method;
            });
    }

    private function defaultPaymentMethodId(?Collection $paymentMethods = null): ?int
    {
        return ($paymentMethods ?? $this->allowedPaymentMethods())->first()?->id;
    }

    private function isCashPaymentMethod(string $methodName): bool
    {
        $normalized = $this->normalizedPaymentMethodName($methodName);

        return str_contains($normalized, 'efectivo') || str_contains($normalized, 'cash');
    }

    private function displayPaymentMethodName(string $methodName): string
    {
        $normalized = $this->normalizedPaymentMethodName($methodName);

        if (str_contains($normalized, 'tarjeta') || str_contains($normalized, 'card') || str_contains($normalized, 'credito') || str_contains($normalized, 'debito')) {
            return 'Tarjeta';
        }

        return 'Efectivo';
    }

    private function normalizedPaymentMethodName(string $methodName): string
    {
        $normalized = mb_strtolower($methodName);

        return strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'Á' => 'a',
            'É' => 'e',
            'Í' => 'i',
            'Ó' => 'o',
            'Ú' => 'u',
        ]);
    }

    private function buildNearExpirationAlerts(Collection $branchProducts): Collection
    {
        return $branchProducts
            ->map(function (BranchProduct $branchProduct) {
                $alert = $this->mapNearExpirationBatch($branchProduct);

                if (!$alert) {
                    return null;
                }

                return [
                    'branch_product_id' => $branchProduct->id,
                    'product_name' => $branchProduct->product?->name ?? 'Producto sin nombre',
                    ...$alert,
                ];
            })
            ->filter()
            ->sortBy([
                ['days_to_expire', 'asc'],
                ['product_name', 'asc'],
            ])
            ->values();
    }

    private function buildRemainingNearExpirationAlertsAfterSale(Sale $sale): array
    {
        $branchProducts = BranchProduct::query()
            ->with([
                'product:id,name',
                'batches' => fn ($query) => $query
                    ->whereIn('status', [
                        ProductBatch::STATUS_ACTIVE,
                        ProductBatch::STATUS_SEASONAL,
                    ])
                    ->where('quantity', '>', 0)
                    ->whereNotNull('expiration_date')
                    ->whereDate('expiration_date', '>=', today())
                    ->whereDate('expiration_date', '<=', now()->addDays(20))
                    ->orderBy('expiration_date')
                    ->orderBy('received_at')
                    ->orderBy('id'),
            ])
            ->where('branch_id', $sale->branch_id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query->where('active', true))
            ->get();

        return $this->buildNearExpirationAlerts($branchProducts)
            ->take(12)
            ->values()
            ->all();
    }

    private function buildPrintJobPayload(Sale $sale): array
    {
        $sale->loadMissing([
            'branch:id,name',
            'employee:id,first_name,last_name',
            'paymentMethod:id,name',
            'details.product:id,name',
        ]);

        return [
            'sale_id' => $sale->id,
            'folio' => $sale->folio,
            'date' => optional($sale->date)->format('d/m/Y H:i'),
            'branch_name' => $sale->branch?->name ?? 'Sucursal',
            'payment_method' => $sale->paymentMethod
                ? $this->displayPaymentMethodName($sale->paymentMethod->name)
                : 'Sin metodo',
            'employee_name' => trim(
                ($sale->employee?->first_name ?? '') . ' ' . ($sale->employee?->last_name ?? '')
            ) ?: 'Sin empleado',
            'user_name' => auth()->user()?->name ?? null,
            'total' => (float) $sale->total,
            'cash_received' => (float) $sale->cash_received,
            'change_due' => (float) $sale->change_due,
            'items' => $sale->details->map(function (SaleDetail $detail) {
                return [
                    'product_name' => $detail->product?->name ?? 'Producto',
                    'quantity' => (float) $detail->quantity,
                    'unit_price' => (float) $detail->unit_price,
                    'subtotal' => (float) $detail->subtotal,
                    'discount_percentage' => (float) ($detail->discount_percentage ?? 0),
                    'discount_amount' => (float) ($detail->discount_amount ?? 0),
                ];
            })->values()->all(),
        ];
    }
}
