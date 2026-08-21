<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
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
use App\Services\SaleCancellationService;
use App\Services\StockMovementService;
use App\Support\SystemPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SalesController extends Controller
{
    use AuthorizesBranchAccess;

    public function index(Request $request)
    {
        $user = $request->user()->loadMissing(['branches', 'role']);
        $allowedBranches = $this->allowedBranches($user);
        $selectorMode = $this->shouldShowBranchSelector($request, $user, $allowedBranches);
        $branch = $selectorMode
            ? null
            : $this->resolveBranch($request, $user, $allowedBranches);

        if (! $selectorMode && ! $branch) {
            return $this->redirectToBranchSetup($request, 'punto de venta');
        }

        $nearExpirationProducts = $selectorMode
            ? collect()
            : $this->nearExpirationProducts($branch, 12);

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
            'productsDB' => [],
            'paymentMethodsDB' => $paymentMethods,
            'defaultPaymentMethodId' => $this->defaultPaymentMethodId($paymentMethods),
            'nearExpirationAlerts' => $selectorMode
                ? []
                : $this->buildNearExpirationAlerts($nearExpirationProducts)->values(),
            'ticketTemplate' => [
                'id' => $ticketTemplate->id,
                'name' => $ticketTemplate->name,
                'slug' => $ticketTemplate->slug,
                'settings' => TicketTemplate::sanitizeSettings($ticketTemplate->settings ?? []),
            ],
        ]);
    }

    public function history(Request $request)
    {
        $user = $request->user()->loadMissing(['branches', 'role']);
        $allowedBranches = $this->allowedBranches($user);
        $allowedBranchIds = $allowedBranches->pluck('id')->map(fn ($id) => (int) $id)->values();

        if ($allowedBranchIds->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'historial de ventas');
        }

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:completed,cancelled'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $branchId = (int) ($filters['branch_id'] ?? 0);
        $branchIds = $branchId > 0 && $allowedBranchIds->contains($branchId)
            ? [$branchId]
            : $allowedBranchIds->all();
        $search = trim((string) ($filters['search'] ?? ''));

        $sales = Sale::query()
            ->with([
                'branch:id,name',
                'employee:id,first_name,last_name',
                'paymentMethod:id,name',
                'details.product:id,name,inventory_unit,unit',
                'details.product.barcodes:id,product_id,code',
                'cancellation.cancelledBy:id,name',
            ])
            ->whereIn('branch_id', $branchIds)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('date', '<=', $date))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

                $query->where(function ($subQuery) use ($like) {
                    $subQuery
                        ->where('folio', 'like', $like)
                        ->orWhereHas('employee', fn ($employeeQuery) => $employeeQuery
                            ->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like))
                        ->orWhereHas('details.product', fn ($productQuery) => $productQuery
                            ->where('name', 'like', $like)
                            ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery->where('code', 'like', $like)));
                });
            })
            ->latest('date')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Sale $sale) => $this->mapRecentSale($sale));

        $ticketTemplate = TicketTemplate::salesTemplate();

        return Inertia::render('Ventas/SalesHistory', [
            'sales' => $sales,
            'branchesDB' => $allowedBranches->values(),
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId > 0 ? $branchId : null,
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
            ],
            'ticketTemplate' => [
                'id' => $ticketTemplate->id,
                'name' => $ticketTemplate->name,
                'slug' => $ticketTemplate->slug,
                'settings' => TicketTemplate::sanitizeSettings($ticketTemplate->settings ?? []),
            ],
        ]);
    }

    public function searchProducts(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'search' => ['required', 'string', 'max:100'],
        ]);

        $user = $request->user()->loadMissing(['branches', 'role']);
        $branch = $this->resolveBranchById($data['branch_id'], $user);
        $term = trim($data['search']);
        $pattern = "%{$term}%";

        $products = BranchProduct::query()
            ->with([
                'product:id,name,image,category_id,cost,sale_price,margin_percentage,active,unit,inventory_unit,pieces_per_box,has_box_presentation,inventory_quantity_mode,cost_per_piece,sale_price_per_piece,cost_per_box,sale_price_per_box',
                'product.category:id,name',
                'product.barcodes:id,product_id,code',
            ])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query
                ->where('active', true)
                ->where('inventory_quantity_mode', '!=', 'legacy_presentation'))
            ->where(function ($query) use ($term, $pattern) {
                $query->where('branch_products.barcode', 'like', $pattern)
                    ->orWhereHas('product', function ($productQuery) use ($term, $pattern) {
                        $productQuery->where('name', 'like', $pattern)
                            ->orWhereHas('barcodes', fn ($barcodeQuery) => $barcodeQuery
                                ->where('code', $term)
                                ->orWhere('code', 'like', $pattern));
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN branch_products.barcode = ? OR EXISTS (
                        SELECT 1 FROM barcodes
                        WHERE barcodes.product_id = branch_products.product_id
                        AND barcodes.code = ?
                    ) THEN 0
                    WHEN EXISTS (
                        SELECT 1 FROM products
                        WHERE products.id = branch_products.product_id
                        AND LOWER(products.name) = LOWER(?)
                    ) THEN 1
                    ELSE 2
                END',
                [$term, $term, $term]
            )
            ->orderBy(
                \App\Models\Product::query()
                    ->select('name')
                    ->whereColumn('products.id', 'branch_products.product_id')
                    ->limit(1)
            )
            ->limit(10)
            ->get()
            ->map(fn (BranchProduct $branchProduct) => $this->mapBranchProduct($branchProduct, false))
            ->values();

        return response()->json(['products' => $products]);
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
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.presentation' => ['nullable', 'in:piece,box'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.original_unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user()->loadMissing(['branches', 'role']);
        $branch = $this->resolveBranchById($data['branch_id'], $user);
        $paymentMethod = $this->allowedPaymentMethods()
            ->firstWhere('id', (int) $data['payment_method_id']);

        if (! $paymentMethod) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'La forma de pago debe ser efectivo o pago con tarjeta.',
            ]);
        }

        if (! $user->employee_id) {
            throw ValidationException::withMessages([
                'employee_id' => 'Tu usuario no tiene un empleado asociado para registrar la venta.',
            ]);
        }

        $data['items'] = collect($data['items'])
            ->sortBy(fn (array $item) => (int) $item['branch_product_id'])
            ->values()
            ->all();

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
                $product = $branchProduct->product;
                if ($product?->inventory_quantity_mode === 'legacy_presentation') {
                    throw ValidationException::withMessages([
                        'items' => 'El producto '.$product->name.' conserva existencias históricas por conciliar antes de venderse.',
                    ]);
                }

                $presentation = $item['presentation'] ?? 'piece';
                $isKilogram = ($product?->inventory_unit ?? $product?->unit) === 'kg';
                $piecesPerBox = (int) ($product?->pieces_per_box ?? 0);
                if ($presentation === 'box' && (! $product?->has_box_presentation || $piecesPerBox < 2)) {
                    throw ValidationException::withMessages([
                        'items' => 'El producto '.($product?->name ?? 'seleccionado').' no tiene una presentación por caja configurada.',
                    ]);
                }

                if (($presentation === 'box' || ! $isKilogram) && abs($quantity - round($quantity)) > 0.0000001) {
                    throw ValidationException::withMessages([
                        'items' => 'Las piezas y cajas deben venderse en cantidades enteras.',
                    ]);
                }

                if ($isKilogram && $quantity > 999.999) {
                    throw ValidationException::withMessages([
                        'items' => 'Los kilogramos permiten hasta 999.999 por renglón.',
                    ]);
                }

                $baseQuantity = $presentation === 'box'
                    ? $quantity * $piecesPerBox
                    : $quantity;
                $originalUnitPrice = $presentation === 'box'
                    ? (float) ($product?->sale_price_per_box ?? 0)
                    : (float) ($product?->sale_price_per_piece ?? $product?->sale_price ?? 0);
                $discountPercentage = round((float) ($item['discount_percentage'] ?? 0), 2);
                $unitPrice = round($originalUnitPrice * (1 - ($discountPercentage / 100)), 2);
                $discountAmount = round(($originalUnitPrice - $unitPrice) * $quantity, 2);
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

                if ($availableStock < $baseQuantity) {
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

                $subtotal = round($quantity * $unitPrice, 2);
                $total += $subtotal;

                $saleDetail = SaleDetail::create([
                    'sale_id' => $sale->id,
                    'product_id' => $branchProduct->product_id,
                    'barcode_id' => $item['barcode_id'] ?? null,
                    'lot_id' => null,
                    'quantity' => $quantity,
                    'sale_unit' => $presentation,
                    'base_quantity' => $baseQuantity,
                    'pieces_per_box' => $presentation === 'box' ? $piecesPerBox : null,
                    'original_unit_price' => $originalUnitPrice,
                    'discount_percentage' => $discountPercentage,
                    'discount_amount' => $discountAmount,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $presentation === 'box'
                        ? ($product?->cost_per_box ?? 0)
                        : ($product?->cost_per_piece ?? $product?->cost ?? 0),
                    'subtotal' => $subtotal,
                ]);

                if ($useBatches) {
                    $manualBatches = $this->allocateBatchesForSale($branchProduct, $baseQuantity);

                    $stockService->move(
                        branchProduct: $branchProduct,
                        type: StockMovement::TYPE_OUT,
                        reason: StockMovement::REASON_SALE,
                        quantity: $baseQuantity,
                        notes: 'Venta generada desde punto de venta',
                        userId: $user->id,
                        batches: [],
                        batchAllocationMethod: StockMovementBatch::ALLOCATION_MANUAL,
                        manualBatches: $manualBatches,
                        saleId: $sale->id,
                        saleDetailId: $saleDetail->id
                    );
                } else {
                    $previousStock = (float) $branchProduct->stock;
                    $newStock = $previousStock - $baseQuantity;

                    $branchProduct->update([
                        'stock' => $newStock,
                    ]);

                    StockMovement::create([
                        'branch_product_id' => $branchProduct->id,
                        'sale_id' => $sale->id,
                        'sale_detail_id' => $saleDetail->id,
                        'type' => StockMovement::TYPE_OUT,
                        'reason' => StockMovement::REASON_SALE,
                        'quantity' => $baseQuantity,
                        'unit_cost' => $presentation === 'box'
                            ? ($product?->cost_per_box ?? 0)
                            : ($product?->cost_per_piece ?? $product?->cost ?? 0),
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'user_id' => $user->id,
                        'notes' => 'Venta generada desde punto de venta',
                    ]);
                }
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
                'folio' => 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                'total' => $total,
                'cash_received' => $cashReceived,
                'change_due' => $isCashPayment ? round($cashReceived - $total, 2) : 0,
            ]);

            return $sale;
        }, 3);

        $expirationAlerts = $this->buildRemainingNearExpirationAlertsAfterSale($sale);

        $responsePayload = [
            'success' => 'Venta registrada correctamente.',
            'sale_folio' => $sale->folio,
            'print_job' => $this->buildPrintJobPayload($sale),
            'expiration_alerts' => $expirationAlerts,
        ];

        if ($request->expectsJson()) {
            return response()->json($responsePayload);
        }

        return back()->with($responsePayload);
    }

    public function cancel(Request $request, Sale $sale, SaleCancellationService $cancellationService)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $user = $request->user()->loadMissing(['branches', 'role']);
        $this->resolveBranchById($sale->branch_id, $user);

        $cancellation = $cancellationService->cancel(
            sale: $sale,
            user: $user,
            reason: $data['reason']
        );

        return back()->with([
            'success' => 'Ticket '.$sale->folio.' cancelado correctamente.',
            'sale_cancellation' => [
                'id' => $cancellation->id,
                'sale_id' => $sale->id,
                'folio' => $sale->folio,
                'amount' => (float) $cancellation->amount,
                'cancelled_at' => $cancellation->cancelled_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function ticket(Request $request, Sale $sale)
    {
        $user = $request->user()->loadMissing(['branches', 'role']);
        $this->resolveBranchById($sale->branch_id, $user);

        return response()->json([
            'print_job' => $this->buildPrintJobPayload($sale),
        ]);
    }

    private function nearExpirationProducts(Branch $branch, int $limit): Collection
    {
        return BranchProduct::query()
            ->with([
                'product:id,name',
                'batches' => fn ($query) => $this->applyNearExpirationBatchConstraints($query),
            ])
            ->withMin([
                'batches as near_expiration_date' => fn ($query) => $this->applyNearExpirationBatchConstraints($query),
            ], 'expiration_date')
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query->where('active', true))
            ->whereHas('batches', fn ($query) => $this->applyNearExpirationBatchConstraints($query))
            ->orderBy('near_expiration_date')
            ->limit($limit)
            ->get();
    }

    private function applyNearExpirationBatchConstraints($query)
    {
        return $query
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
            ->orderBy('id');
    }

    private function mapBranchProduct(BranchProduct $branchProduct, bool $includeNearExpirationAlert = true): array
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
            'price' => (float) ($product?->sale_price_per_piece ?? $product?->sale_price ?? 0),
            'sale_price_per_piece' => (float) ($product?->sale_price_per_piece ?? $product?->sale_price ?? 0),
            'sale_price_per_box' => (float) ($product?->sale_price_per_box ?? 0),
            'has_box_presentation' => (bool) $product?->has_box_presentation,
            'pieces_per_box' => (int) ($product?->pieces_per_box ?? 0),
            'inventory_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
            'inventory_quantity_mode' => $product?->inventory_quantity_mode ?? 'base',
            'cost' => (float) ($product?->cost ?? 0),
            'margin_percentage' => (float) ($product?->margin_percentage ?? 0),
            'stock' => (float) ($branchProduct->stock ?? 0),
            'tracks_batches' => (bool) $branchProduct->tracks_batches,
            // La alerta de caducidad se calcula fuera del buscador para mantenerlo inmediato.
            'near_expiration_alert' => $includeNearExpirationAlert
                ? $this->mapNearExpirationBatch($branchProduct)
                : null,
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

    private function mapRecentSale(Sale $sale): array
    {
        return [
            'id' => (int) $sale->id,
            'folio' => $sale->folio ?: 'V-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
            'date' => optional($sale->date)->toISOString(),
            'date_display' => optional($sale->date)->format('d/m/Y H:i') ?? '-',
            'branch' => $sale->branch?->name ?? '-',
            'seller' => trim(($sale->employee?->first_name ?? '').' '.($sale->employee?->last_name ?? '')) ?: 'Sin vendedor',
            'payment_method' => $sale->paymentMethod
                ? $this->displayPaymentMethodName($sale->paymentMethod->name)
                : 'Sin metodo',
            'cash_box_number' => $sale->cash_box_number ?: '1',
            'status' => $sale->status,
            'status_label' => $sale->status === 'cancelled' ? 'Cancelada' : 'Completada',
            'can_cancel' => $sale->status === 'completed' && ! $sale->cancellation,
            'total' => (float) $sale->total,
            'details' => $sale->details
                ->map(fn (SaleDetail $detail) => $this->mapRecentSaleDetail($detail))
                ->values()
                ->all(),
            'cancellation' => $sale->cancellation ? [
                'reason' => $sale->cancellation->reason,
                'amount' => (float) $sale->cancellation->amount,
                'cancelled_at_display' => optional($sale->cancellation->cancelled_at)->format('d/m/Y H:i') ?? '-',
                'cancelled_by' => $sale->cancellation->cancelledBy?->name ?? 'Sin usuario',
            ] : null,
        ];
    }

    private function mapRecentSaleDetail(SaleDetail $detail): array
    {
        $product = $detail->product;
        $unit = strtolower($product?->inventory_unit ?? $product?->unit ?? 'pza') === 'kg' ? 'kg' : 'pza';
        $baseQuantity = (float) ($detail->base_quantity ?? $detail->quantity ?? 0);

        return [
            'id' => (int) $detail->id,
            'product' => $product?->name ?? 'Producto sin nombre',
            'code' => $product?->barcodes?->first()?->code ?? '-',
            'quantity_display' => $this->formatQuantityForTicket($baseQuantity, $unit),
            'unit_price' => (float) $detail->unit_price,
            'discount_amount' => (float) ($detail->discount_amount ?? 0),
            'subtotal' => (float) $detail->subtotal,
        ];
    }

    private function formatQuantityForTicket(float $quantity, string $unit): string
    {
        if ($unit !== 'kg') {
            return ((string) (int) round($quantity)).' pzas';
        }

        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') ?: '0';

        return $formatted.' kg';
    }

    private function shouldShowBranchSelector(Request $request, $user, $allowedBranches): bool
    {
        return ! $request->filled('branch') && $allowedBranches->count() > 1;
    }

    private function resolveBranch(Request $request, $user, $allowedBranches): ?Branch
    {
        $branchId = $request->query('branch');

        if (! $branchId && $allowedBranches->count() === 1) {
            $branchId = $allowedBranches->first()->id;
        }

        if (! $branchId && $allowedBranches->isNotEmpty()) {
            $branchId = $allowedBranches->first()->id;
        }

        if (! $branchId) {
            return null;
        }

        return $this->resolveBranchById($branchId, $user);
    }

    private function resolveBranchById($branchId, $user): Branch
    {
        $query = Branch::query()->whereKey($branchId)->where('active', true);

        if (! $user->hasPermission(SystemPermission::BRANCHES_ACCESS_ALL)) {
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

        if (! $batch) {
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

                if (! $alert) {
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
                ($sale->employee?->first_name ?? '').' '.($sale->employee?->last_name ?? '')
            ) ?: 'Sin empleado',
            'user_name' => auth()->user()?->name ?? null,
            'total' => (float) $sale->total,
            'cash_received' => (float) $sale->cash_received,
            'change_due' => (float) $sale->change_due,
            'items' => $sale->details->map(function (SaleDetail $detail) {
                return [
                    'product_name' => $detail->product?->name ?? 'Producto',
                    'quantity' => (float) $detail->quantity,
                    'sale_unit' => $detail->sale_unit ?? 'piece',
                    'pieces_per_box' => $detail->pieces_per_box,
                    'unit_price' => (float) $detail->unit_price,
                    'subtotal' => (float) $detail->subtotal,
                    'discount_percentage' => (float) ($detail->discount_percentage ?? 0),
                    'discount_amount' => (float) ($detail->discount_amount ?? 0),
                ];
            })->values()->all(),
        ];
    }
}
