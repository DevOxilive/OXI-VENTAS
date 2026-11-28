<?php

namespace App\Http\Controllers\Inventory;

use App\Events\PurchaseWorkflowChanged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Concerns\ValidatesRecordVersion;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ProductDepartment;
use App\Models\StockMovement;
use App\Models\SystemAudit;
use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use App\Services\StockMovementService;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    use AuthorizesBranchAccess, ValidatesRecordVersion;

    public function __construct(
        private readonly ProductSearchService $productSearch,
        private readonly StockMovementService $stockMovementService,
    ) {}

    public function salesPurchaseLists(Request $request)
    {
        $branches = $this->accessiblePurchaseBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'listas de compra');
        }

        $branch = $this->selectedPurchaseBranch($request, $branches);

        if (! $branch) {
            return Inertia::render('Inventory/PurchaseReport', [
                'selectorMode' => true,
                'currentBranch' => null,
                'branchesDB' => $this->mapPurchaseBranches($branches),
                'productsDB' => ['data' => []],
                'reportsDB' => ['data' => []],
                'filters' => [],
                'categoriesDB' => [],
            ]);
        }

        return $this->index($request, $branch);
    }

    public function salesPurchaseOrders(Request $request)
    {
        $branches = $this->accessiblePurchaseBranches($request);

        if ($branches->isEmpty()) {
            return $this->redirectToBranchSetup($request, 'ordenes de compra');
        }

        $branch = $this->selectedPurchaseBranch($request, $branches);

        if (! $branch) {
            return Inertia::render('Inventory/BranchPurchaseOrders', [
                'selectorMode' => true,
                'currentBranch' => null,
                'branchesDB' => $this->mapPurchaseBranches($branches),
                'ordersDB' => ['data' => []],
                'filters' => ['status' => 'ALL'],
            ]);
        }

        return $this->reportsIndex($request, $branch);
    }

    public function reportsIndex(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $perPage = TablePagination::resolvePerPage($request);
        $status = $request->input('status', 'ALL');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $allowedStatuses = [
            'ALL',
            'PENDING',
            'APPROVED',
            'REJECTED',
            PurchaseOrder::STATUS_REVIEW,
            PurchaseOrder::STATUS_COMPLETED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = PurchaseOrder::STATUS_GENERATED;
        }

        $filters = [
            'status' => $status,
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
        ];

        $orders = PurchaseOrder::query()
            ->withCount('items')
            ->where('branch_id', $branch->id)
            ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
            ->when($filters['status'] === 'PENDING', fn ($query) => $query
                ->where('status', PurchaseOrder::STATUS_GENERATED)
                ->where('review_status', PurchaseOrder::REVIEW_PENDING))
            ->when($filters['status'] === 'APPROVED', fn ($query) => $query
                ->where('status', PurchaseOrder::STATUS_GENERATED)
                ->where('review_status', PurchaseOrder::REVIEW_APPROVED))
            ->when($filters['status'] === 'REJECTED', fn ($query) => $query
                ->where('status', PurchaseOrder::STATUS_GENERATED)
                ->where('review_status', PurchaseOrder::REVIEW_REJECTED))
            ->when(in_array($filters['status'], [PurchaseOrder::STATUS_REVIEW, PurchaseOrder::STATUS_COMPLETED], true), fn ($query) => $query->where('status', $filters['status']))
            ->when($search !== '', fn ($query) => $query->where('folio', 'like', "%{$search}%"))
            ->when($dateFrom, fn ($query) => $query->whereDate('generated_at', '>=', $dateFrom))
            ->when($dateTo, fn ($query) => $query->whereDate('generated_at', '<=', $dateTo));

        $orders = $orders
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString();

        $reviewActivity = $this->reviewActivityByOrderId(
            $orders->getCollection()->pluck('id')->all(),
        );

        $orders->through(function (PurchaseOrder $order) use ($reviewActivity) {
            $activity = $reviewActivity[$order->id] ?? 'Sin abrir';

            return [
                'id' => $order->id,
                'folio' => $this->displayPurchaseFolio($order->folio),
                'stored_folio' => $order->folio,
                'status' => $order->status,
                'review_status' => $order->review_status,
                'review_status_label' => $this->reviewStatusLabel($order->review_status),
                'review_activity' => $activity,
                'workflow_status_label' => $this->workflowStatusLabel($order, $activity),
                'workflow_status_tone' => $this->workflowStatusTone($order, $activity),
                'status_label' => $this->statusLabel(
                    $order->status,
                    $order->review_status,
                    (bool) $order->inventory_edited_at,
                ),
                'items_count' => $order->items_count,
                'display_date' => $order->completed_at ?? $order->generated_at ?? $order->created_at,
            ];
        });

        return Inertia::render('Inventory/BranchPurchaseOrders', [
            'currentBranch' => $branch,
            'selectorMode' => false,
            'branchesDB' => $this->mapPurchaseBranches($this->accessiblePurchaseBranches($request)),
            'ordersDB' => $orders,
            'filters' => $filters,
            'notificationSummary' => $this->salesPurchaseOrderNotificationSummary($request, $branch),
        ]);
    }

    public function create(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->index($request, $branch);
    }

    public function store(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $validated = $request->validate([
            'generate_order' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
            'items.*.purchase_presentation' => ['nullable', 'in:Pieza,Paquete,Caja,Kilo'],
            'items.*.package_quantity' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
        ]);

        $generateOrder = (bool) ($validated['generate_order'] ?? false);
        $report = DB::transaction(function () use ($branch, $generateOrder, $request, $validated) {
            $report = PurchaseOrder::query()
                ->where('branch_id', $branch->id)
                ->where('status', PurchaseOrder::STATUS_DRAFT)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $report) {
                $report = PurchaseOrder::create([
                    'branch_id' => $branch->id,
                    'user_id' => $request->user()?->id,
                    'source' => PurchaseOrder::SOURCE_CENTRAL,
                    'folio' => $generateOrder ? $this->makeGeneratedFolio() : null,
                    'status' => $generateOrder
                        ? PurchaseOrder::STATUS_GENERATED
                        : PurchaseOrder::STATUS_DRAFT,
                    'generated_at' => $generateOrder ? now() : null,
                ]);
            }

            $this->syncItems($report, $branch, $validated['items']);
            $this->refreshTotals($report);

            if ($generateOrder && $report->status === PurchaseOrder::STATUS_DRAFT) {
                $report->update([
                    'folio' => $this->makeGeneratedFolio(),
                    'status' => PurchaseOrder::STATUS_GENERATED,
                    'generated_at' => now(),
                ]);
            }

            return $report;
        });

        return redirect()->route('ventas.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with(
            'success',
            $generateOrder
                ? 'Solicitud enviada a Compras correctamente.'
                : 'Borrador guardado correctamente.'
        );
    }

    public function update(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);

        $validated = $this->validateOrderPayload($request);
        DB::transaction(function () use ($purchaseReport, $request, $branch, $validated) {
            $lockedOrder = $this->lockCurrentVersion($request, $purchaseReport);
            abort_unless($lockedOrder->status === PurchaseOrder::STATUS_DRAFT, 422, 'Solo se pueden modificar listas en borrador.');
            abort_if($lockedOrder->general_purchase_order_id, 422, 'La solicitud ya forma parte de una orden general.');
            $this->syncItems($lockedOrder, $branch, $validated['items']);
            $this->refreshTotals($lockedOrder);
        }, 3);

        return redirect()->back()->with('success', 'Borrador compartido actualizado correctamente.');
    }

    public function generate(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        $validated = $this->validateOrderPayload($request);

        $generatedOrder = DB::transaction(function () use ($branch, $purchaseReport, $request, $validated) {
            $lockedOrder = $this->lockCurrentVersion($request, $purchaseReport);
            abort_unless($lockedOrder->status === PurchaseOrder::STATUS_DRAFT, 422);
            abort_if($lockedOrder->general_purchase_order_id, 422, 'La solicitud ya forma parte de una orden general.');
            $this->syncItems($lockedOrder, $branch, $validated['items']);
            $this->refreshTotals($lockedOrder);

            $generatedOrder = $lockedOrder->replicate([
                'folio',
                'status',
                'generated_at',
                'created_at',
                'updated_at',
            ]);
            $generatedOrder->forceFill([
                'folio' => $this->makeGeneratedFolio(),
                'status' => PurchaseOrder::STATUS_GENERATED,
                'generated_at' => now(),
            ])->save();

            foreach ($lockedOrder->items as $item) {
                $generatedOrder->items()->create($item->only([
                    'branch_product_id',
                    'product_id',
                    'current_stock',
                    'min_stock',
                    'requested_quantity',
                    'purchase_presentation',
                    'package_quantity',
                    'units_per_package',
                    'estimated_price',
                    'estimated_total',
                    'status',
                ]));
            }

            $this->refreshTotals($generatedOrder);
            $lockedOrder->delete();

            return $generatedOrder;
        }, 3);

        return redirect()->route('ventas.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with(
            'success',
            'Solicitud enviada a Compras correctamente.'
        );
    }

    public function complete(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:9999.999'],
            'items.*.receipt_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $receivedItems = collect($validated['items'])->keyBy(fn ($item) => (int) $item['id']);

        $inventoryWasPreloaded = DB::transaction(function () use ($purchaseReport, $receivedItems, $request) {
            $lockedOrder = $this->lockCurrentVersion($request, $purchaseReport);
            abort_unless($lockedOrder->status === PurchaseOrder::STATUS_REVIEW, 422, 'La orden debe estar por revisar antes de confirmar la recepcion.');
            $items = $lockedOrder->items()->with('branchProduct.product')->lockForUpdate()->get();

            if ($receivedItems->keys()->sort()->values()->all() !== $items->pluck('id')->sort()->values()->all()) {
                throw ValidationException::withMessages([
                    'items' => 'Debes confirmar la recepcion de todos los productos de la orden.',
                ]);
            }

            $generalItems = $lockedOrder->general_purchase_order_id
                ? GeneralPurchaseOrderItem::query()
                    ->where('general_purchase_order_id', $lockedOrder->general_purchase_order_id)
                    ->with('plannedBatches')
                    ->get()
                    ->keyBy('product_id')
                : collect();
            $inventoryWasPreloaded = false;

            foreach ($items as $item) {
                $received = $receivedItems->get((int) $item->id);
                $receivedQuantity = (float) $received['received_quantity'];
                $expectedQuantity = (float) ($item->purchased_quantity ?? $item->requested_quantity);
                $generalItem = $generalItems->get($item->product_id);
                $enteredQuantity = $this->registerPreloadedInventory(
                    item: $item,
                    generalItem: $generalItem,
                    receivedQuantity: $receivedQuantity,
                    userId: $lockedOrder->generalOrder?->created_by ?? $request->user()?->id,
                    orderFolio: $lockedOrder->folio,
                );
                $inventoryWasPreloaded = $inventoryWasPreloaded || $enteredQuantity > 0;
                $receiptNotes = $this->receiptNotesWithPreloadDifference(
                    notes: $received['receipt_notes'] ?? null,
                    receivedQuantity: $receivedQuantity,
                    enteredQuantity: $enteredQuantity,
                );

                $item->update([
                    'received_quantity' => $receivedQuantity,
                    'receipt_notes' => $receiptNotes,
                    'actual_total' => round($receivedQuantity * (float) $item->actual_price, 2),
                    'status' => $receivedQuantity <= 0
                        ? PurchaseOrderItem::STATUS_UNAVAILABLE
                        : (abs($receivedQuantity - $expectedQuantity) < 0.001
                            ? PurchaseOrderItem::STATUS_PURCHASED
                            : PurchaseOrderItem::STATUS_ADJUSTED),
                ]);
            }

            $this->refreshTotals($lockedOrder);

            $lockedOrder->update([
                'status' => PurchaseOrder::STATUS_COMPLETED,
                'completed_by' => $request->user()?->id,
                'completed_at' => now(),
            ]);

            return $inventoryWasPreloaded;
        }, 3);

        PurchaseWorkflowChanged::dispatch('receipt_completed', $purchaseReport->id);

        return redirect()->back()->with(
            'success',
            $inventoryWasPreloaded
                ? 'Recepcion confirmada e inventario actualizado correctamente.'
                : 'Orden completada correctamente.'
        );
    }

    /**
     * Convierte la parte confirmada de una precarga en existencias reales.
     * La precarga sigue siendo el limite: un sobrante se conserva en la
     * recepcion como diferencia, pero no inventa lotes ni aumenta el stock.
     */
    private function registerPreloadedInventory(
        PurchaseOrderItem $item,
        ?GeneralPurchaseOrderItem $generalItem,
        float $receivedQuantity,
        ?int $userId,
        ?string $orderFolio,
    ): float {
        if (! $generalItem || $receivedQuantity <= 0 || ! $item->branchProduct) {
            return 0;
        }

        $remainingToEnter = round($receivedQuantity, 3);
        $enteredQuantity = 0.0;

        foreach ($generalItem->plannedBatches->sortBy('id') as $plannedBatch) {
            $allocation = collect($plannedBatch->allocations ?? [])
                ->first(fn (array $row) => (int) ($row['branch_id'] ?? 0) === (int) $item->branchProduct->branch_id);
            $plannedQuantity = round((float) ($allocation['quantity'] ?? 0), 3);

            if ($plannedQuantity <= 0 || $remainingToEnter <= 0) {
                continue;
            }

            $quantityToEnter = min($remainingToEnter, $plannedQuantity);
            if (blank($plannedBatch->lot_number) || ! $plannedBatch->expiration_date) {
                throw ValidationException::withMessages([
                    'items' => "La precarga de {$item->product?->name} necesita numero de lote y caducidad antes de confirmar la recepcion.",
                ]);
            }

            $this->stockMovementService->move(
                branchProduct: $item->branchProduct,
                type: StockMovement::TYPE_IN,
                reason: StockMovement::REASON_PURCHASE,
                quantity: $quantityToEnter,
                notes: "Recepcion de {$orderFolio}. Precarga de compra central.",
                userId: $userId,
                batches: [[
                    'lot_number' => $plannedBatch->lot_number,
                    'expiration_date' => $plannedBatch->expiration_date->toDateString(),
                    'received_at' => now()->toDateString(),
                    'supplier' => $plannedBatch->supplier,
                    'quantity' => $quantityToEnter,
                ]],
            );

            $remainingToEnter = round($remainingToEnter - $quantityToEnter, 3);
            $enteredQuantity = round($enteredQuantity + $quantityToEnter, 3);
        }

        return $enteredQuantity;
    }

    private function receiptNotesWithPreloadDifference(?string $notes, float $receivedQuantity, float $enteredQuantity): ?string
    {
        $notes = trim((string) $notes);
        $difference = round($receivedQuantity - $enteredQuantity, 3);

        if ($difference <= 0.0009) {
            return $notes !== '' ? $notes : null;
        }

        $preloadNote = "Sobrante registrado: {$difference}. Queda pendiente de aclarar; no se agrego al inventario.";

        return $notes === ''
            ? $preloadNote
            : "{$notes}\n{$preloadNote}";
    }

    public function destroy(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        DB::transaction(function () use ($purchaseReport, $request) {
            $lockedOrder = $this->lockCurrentVersion($request, $purchaseReport);
            abort_unless($lockedOrder->status === PurchaseOrder::STATUS_DRAFT, 422);
            $lockedOrder->delete();
        });

        return redirect()->back()->with('success', 'Borrador eliminado correctamente.');
    }

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $perPage = TablePagination::resolvePerPage($request);

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'department_ids' => $this->integerList($request->input('department_ids', [])),
            'category_ids' => $this->integerList($request->input('category_ids', [])),
            'product_ids' => $this->integerList($request->input('product_ids', [])),
            'stock' => $request->input('stock', ''),
            'per_page' => $perPage,
        ];

        $products = $this->productsQuery($branch, $filters)
            ->orderBy('stock')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product?->id,
                'name' => $item->product?->name ?? 'Producto sin nombre',
                'code' => $item->barcode ?? '',
                'primary_code' => $item->product?->barcodes?->first()?->code ?: ($item->barcode ?? ''),
                'main_barcode' => $item->product?->barcodes?->first()?->code ?? '',
                'barcodes' => $item->product?->barcodes?->pluck('code')->values() ?? [],
                'category_id' => $item->product?->category?->id,
                'product_department_id' => $item->product?->category?->product_department_id,
                'product_department_name' => $item->product?->category?->productDepartment?->name ?? 'Sin departamento',
                'department' => $item->product?->category?->productDepartment?->name ?? 'Sin departamento',
                'category_name' => $item->product?->category?->name ?? 'Sin categoria',
                'category' => $item->product?->category?->name ?? 'Sin categoria',
                'stock' => (float) $item->stock,
                'min_stock' => (float) $item->min_stock,
                'inventory_unit' => $item->product?->inventory_unit ?? $item->product?->unit ?? 'pza',
                'has_box_presentation' => (bool) $item->product?->has_box_presentation,
                'pieces_per_box' => $item->product?->pieces_per_box,
                'has_package_presentation' => (bool) $item->product?->has_package_presentation,
                'pieces_per_package' => $item->product?->pieces_per_package,
                'label' => trim(($item->product?->name ?? 'Producto sin nombre')
                    .' - '
                    .($item->product?->barcodes?->first()?->code ?: ($item->barcode ?: 'Sin codigo'))),
            ]);

        $reports = PurchaseOrder::query()
            ->with([
                'items.branchProduct.product.barcodes',
            ])
            ->withCount('items')
            ->where('branch_id', $branch->id)
            ->where('status', PurchaseOrder::STATUS_DRAFT)
            ->latest()
            ->paginate(1, ['*'], 'lists_page')
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'folio' => $order->folio,
                'status' => $order->status,
                'items_count' => $order->items_count,
                'created_at' => $order->created_at,
                'record_version' => $order->updated_at?->toJSON(),
                'display_date' => $order->completed_at ?? $order->generated_at ?? $order->created_at,
                'items' => $order->items->map(function (PurchaseOrderItem $item) {
                    $branchProduct = $item->branchProduct;
                    $product = $branchProduct?->product;

                    return [
                        'branch_product_id' => $item->branch_product_id,
                        'name' => $product?->name ?? 'Producto sin nombre',
                        'code' => $product?->barcodes?->first()?->code ?: ($branchProduct?->barcode ?? ''),
                        'current_stock' => $item->current_stock,
                        'min_stock' => $item->min_stock,
                        'base_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
                        'inventory_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
                        'requested_quantity' => $item->requested_quantity,
                        'purchase_presentation' => $item->purchase_presentation,
                        'package_quantity' => $item->package_quantity,
                        'units_per_package' => $item->units_per_package,
                        'purchased_quantity' => $item->purchased_quantity,
                        'status' => $item->status,
                        'branch_product' => [
                            'id' => $branchProduct?->id,
                            'barcode' => $branchProduct?->barcode,
                            'stock' => $branchProduct?->stock,
                            'min_stock' => $branchProduct?->min_stock,
                            'product' => [
                                'id' => $product?->id,
                                'name' => $product?->name ?? 'Producto sin nombre',
                                'inventory_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
                                'has_box_presentation' => (bool) $product?->has_box_presentation,
                                'pieces_per_box' => $product?->pieces_per_box,
                                'has_package_presentation' => (bool) $product?->has_package_presentation,
                                'pieces_per_package' => $product?->pieces_per_package,
                                'barcodes' => $product?->barcodes?->map(fn ($barcode) => [
                                    'id' => $barcode->id,
                                    'code' => $barcode->code,
                                ])->values() ?? [],
                            ],
                        ],
                    ];
                })->values(),
            ]);

        return Inertia::render('Inventory/PurchaseReport', [
            'selectorMode' => false,
            'currentBranch' => $branch,
            'branchesDB' => $this->mapPurchaseBranches($this->accessiblePurchaseBranches($request)),
            'productsDB' => $products,
            'reportsDB' => $reports,
            'filters' => $filters,
            'productDepartmentsDB' => $this->productDepartmentOptions($branch),
            'categoriesDB' => $this->categoryOptions($branch),
            'productOptionsDB' => $this->productOptions($branch),
        ]);
    }

    public function show(Request $request, Branch $branch, PurchaseOrder $purchaseReport)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        return redirect()->route('inventory.branches.purchase-reports.index', [
            'branch' => $branch->id,
        ]);
    }

    public function reportOrder(Request $request, Branch $branch, PurchaseOrder $purchaseReport)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        $purchaseReport->load([
            'branch:id,name',
            'user:id,name',
            'generalOrder.creator:id,name',
            'items.branchProduct.product.barcodes',
            'items.branchProduct.product.category.productDepartment',
        ]);
        $reviewActivity = $this->reviewActivityByOrderId([$purchaseReport->id])[$purchaseReport->id] ?? 'Sin abrir';

        return response()->json([
            'id' => $purchaseReport->id,
            'folio' => $purchaseReport->folio,
            'status' => $purchaseReport->status,
            'status_label' => $this->statusLabel(
                $purchaseReport->status,
                $purchaseReport->review_status,
                (bool) $purchaseReport->inventory_edited_at,
            ),
            'review_status' => $purchaseReport->review_status,
            'review_status_label' => $this->reviewStatusLabel($purchaseReport->review_status),
            'review_activity' => $reviewActivity,
            'workflow_status_label' => $this->workflowStatusLabel($purchaseReport, $reviewActivity),
            'workflow_status_tone' => $this->workflowStatusTone($purchaseReport, $reviewActivity),
            'change_history' => $this->sourceOrderChangeHistory($purchaseReport->id),
            'record_version' => $purchaseReport->updated_at?->toJSON(),
            'requested_at' => $purchaseReport->generated_at ?? $purchaseReport->created_at,
            'items_count' => $purchaseReport->items->count(),
            'branch' => $purchaseReport->branch ? ['id' => $purchaseReport->branch->id, 'name' => $purchaseReport->branch->name] : null,
            'user' => $purchaseReport->user ? ['id' => $purchaseReport->user->id, 'name' => $purchaseReport->user->name] : null,
            'general_order' => $purchaseReport->generalOrder ? [
                'id' => $purchaseReport->generalOrder->id,
                'folio' => $purchaseReport->generalOrder->folio,
                'created_by' => $purchaseReport->generalOrder->creator ? [
                    'id' => $purchaseReport->generalOrder->creator->id,
                    'name' => $purchaseReport->generalOrder->creator->name,
                ] : null,
            ] : null,
            'items' => $purchaseReport->items->map(function (PurchaseOrderItem $item) {
                $branchProduct = $item->branchProduct;
                $product = $branchProduct?->product;

                return [
                    'id' => $item->id,
                    'branch_product_id' => $item->branch_product_id,
                    'product_name' => $product?->name ?? 'Producto sin nombre',
                    'product_code' => $product?->barcodes?->first()?->code ?: ($branchProduct?->barcode ?? ''),
                    'product_department_name' => $product?->category?->productDepartment?->name ?? 'Sin departamento',
                    'department' => $product?->category?->productDepartment?->name ?? 'Sin departamento',
                    'category_name' => $product?->category?->name ?? 'Sin categoria',
                    'purchase_presentation' => $item->purchase_presentation ?? 'Pieza',
                    'package_quantity' => (float) ($item->package_quantity ?? $item->requested_quantity),
                    'units_per_package' => (float) ($item->units_per_package ?? 1),
                    'requested_quantity' => (float) $item->requested_quantity,
                    'received_quantity' => $item->received_quantity === null
                        ? null
                        : (float) $item->received_quantity,
                    'receipt_notes' => $item->receipt_notes,
                    'status' => $item->status,
                    'status_label' => $this->receiptStatusLabel($item),
                ];
            })->values(),
        ]);
    }

    private function productsQuery(Branch $branch, array $filters)
    {
        return BranchProduct::query()
            ->with([
                'product.category.productDepartment',
                'product.barcodes',
            ])
            ->where('branch_id', $branch->id)
            ->when($filters['search'] !== '', function ($query) use ($branch, $filters) {
                $this->productSearch->constrainBranchProducts(
                    $query,
                    $filters['search'],
                    'branch_products.product_id',
                    'batches',
                    new ProductSearchOptions(
                        branchIds: [$branch->id],
                        limit: (int) config('product_search.max_results'),
                        includeLotNumbers: false,
                        inventorySqlPrefilter: true,
                    ),
                    orderByRelevance: true,
                    resetExistingOrder: true,
                );
            })
            ->when($filters['department_ids'], function ($query, $departmentIds) {
                $query->whereHas('product.category', function ($categoryQuery) use ($departmentIds) {
                    $categoryQuery->whereIn('product_department_id', $departmentIds);
                });
            })
            ->when($filters['category_ids'], function ($query, $categoryIds) {
                $query->whereHas('product', function ($productQuery) use ($categoryIds) {
                    $productQuery->whereIn('category_id', $categoryIds);
                });
            })
            ->when($filters['product_ids'], function ($query, $productIds) {
                $query->whereIn('product_id', $productIds);
            })
            ->when($filters['stock'] === 'LOW', function ($query) {
                $query->whereColumn('stock', '<=', 'min_stock')
                    ->where('stock', '>', 0);
            })
            ->when($filters['stock'] === 'OUT', function ($query) {
                $query->where('stock', '<=', 0);
            });
    }

    private function categoryOptions(Branch $branch)
    {
        return Category::query()
            ->select(['categories.id', 'categories.product_department_id', 'categories.name'])
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->where('branch_products.branch_id', $branch->id)
            ->distinct()
            ->orderBy('categories.name')
            ->get();
    }

    private function productDepartmentOptions(Branch $branch)
    {
        return ProductDepartment::query()
            ->select(['product_departments.id', 'product_departments.name'])
            ->join('categories', 'categories.product_department_id', '=', 'product_departments.id')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->where('branch_products.branch_id', $branch->id)
            ->distinct()
            ->orderBy('product_departments.name')
            ->get();
    }

    private function productOptions(Branch $branch)
    {
        return BranchProduct::query()
            ->with(['product.category:id,product_department_id,name', 'product.barcodes:id,product_id,code'])
            ->where('branch_id', $branch->id)
            ->orderBy('id')
            ->get()
            ->map(function (BranchProduct $branchProduct) {
                $product = $branchProduct->product;

                return [
                    'id' => $product?->id,
                    'name' => trim(($product?->name ?? 'Producto sin nombre')
                        .' - '
                        .($product?->barcodes?->first()?->code ?: ($branchProduct->barcode ?: 'Sin codigo'))),
                    'category_id' => $product?->category_id,
                    'product_department_id' => $product?->category?->product_department_id,
                ];
            })
            ->filter(fn (array $product) => filled($product['id']))
            ->values();
    }

    private function integerList(mixed $value): array
    {
        return collect(is_array($value) ? $value : explode(',', (string) $value))
            ->map(fn ($item) => (int) $item)
            ->filter(fn (int $item) => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function accessiblePurchaseBranches(Request $request)
    {
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesion.');

        return $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name')
            ->get();
    }

    private function selectedPurchaseBranch(Request $request, $branches): ?Branch
    {
        abort_if($branches->isEmpty(), 403, 'No tienes sucursales asignadas.');

        $requestedBranch = trim((string) $request->query('branch', ''));

        if ($requestedBranch !== '') {
            $branch = $branches->first(fn (Branch $branch) => (string) $branch->id === $requestedBranch
                || (string) $branch->slug === $requestedBranch
            );

            abort_unless($branch, 403, 'No tienes acceso a la sucursal seleccionada.');

            return $branch;
        }

        return $branches->count() === 1 ? $branches->first() : null;
    }

    private function mapPurchaseBranches($branches): array
    {
        return $branches
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'slug' => $branch->slug,
                'color' => $branch->color,
            ])
            ->values()
            ->all();
    }

    private function salesPurchaseOrderNotificationSummary(Request $request, Branch $branch): array
    {
        $items = PurchaseOrder::query()
            ->withCount('items')
            ->where('branch_id', $branch->id)
            ->where(function ($query) {
                $query
                    ->where('status', PurchaseOrder::STATUS_REVIEW)
                    ->orWhereNotNull('inventory_edited_at')
                    ->orWhereIn('review_status', [
                        PurchaseOrder::REVIEW_APPROVED,
                        PurchaseOrder::REVIEW_REJECTED,
                    ]);
            })
            ->latest('updated_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (PurchaseOrder $order) {
                $badge = match (true) {
                    $order->status === PurchaseOrder::STATUS_REVIEW => 'Por revisar',
                    $order->review_status === PurchaseOrder::REVIEW_APPROVED => 'Aprobada',
                    $order->review_status === PurchaseOrder::REVIEW_REJECTED => 'Rechazada',
                    (bool) $order->inventory_edited_at => 'Editada',
                    default => 'Aviso',
                };

                $tone = match ($badge) {
                    'Aprobada' => 'green',
                    'Rechazada' => 'red',
                    'Editada' => 'blue',
                    default => 'amber',
                };

                return [
                    'id' => $order->id,
                    'branch_id' => $order->branch_id,
                    'folio' => $order->folio,
                    'status' => $order->status,
                    'title' => $order->folio,
                    'description' => $order->items_count.' producto(s) · '.$this->statusLabel($order->status, $order->review_status),
                    'meta' => $order->inventory_edited_at
                        ? 'Inventario hizo ajustes en esta orden.'
                        : 'Da seguimiento a esta Orden de compra.',
                    'badge' => $badge,
                    'tone' => $tone,
                    'updated_at' => $order->updated_at,
                ];
            });

        return [
            'mode' => 'sales',
            'count' => $items->count(),
            'items' => $items,
        ];
    }

    /**
     * Resume quién tiene abierta cada solicitud sin exponer detalles internos.
     * La vigencia coincide con el latido de presencia enviado por la interfaz.
     *
     * @param array<int, int> $orderIds
     * @return array<int, string>
     */
    private function reviewActivityByOrderId(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return DB::table('purchase_review_presence')
            ->join('users', 'users.id', '=', 'purchase_review_presence.user_id')
            ->whereIn('purchase_order_id', $orderIds)
            ->get(['purchase_order_id', 'users.name', 'last_seen_at'])
            ->groupBy('purchase_order_id')
            ->map(function ($visits): string {
                $activeNames = $visits
                    ->filter(fn ($visit) => $visit->last_seen_at && now()->diffInSeconds($visit->last_seen_at) <= 75)
                    ->pluck('name')
                    ->unique()
                    ->implode(', ');

                return $activeNames !== ''
                    ? 'Abierta por: '.$activeNames
                    : ($visits->isNotEmpty() ? 'En revisión' : 'Sin abrir');
            })
            ->mapWithKeys(fn (string $activity, $orderId) => [(int) $orderId => $activity])
            ->all();
    }

    private function workflowStatusLabel(PurchaseOrder $order, string $activity): string
    {
        // Cuando Compras ya reunió la solicitud en una compra central, Ventas no
        // necesita interpretar "aprobada" como si el proceso hubiera terminado.
        if ($order->general_purchase_order_id && $order->status === PurchaseOrder::STATUS_GENERATED) return 'En compra';
        if ($order->review_status === PurchaseOrder::REVIEW_APPROVED) return 'Aprobada';
        if ($order->review_status === PurchaseOrder::REVIEW_REJECTED) return 'Rechazada';
        if ($order->status !== PurchaseOrder::STATUS_GENERATED) return $this->statusLabel($order->status, $order->review_status);
        if ($order->inventory_edited_at) return 'Modificada';

        return $activity === 'Sin abrir' ? 'Pendiente' : $activity;
    }

    private function workflowStatusTone(PurchaseOrder $order, string $activity): string
    {
        if ($order->general_purchase_order_id && $order->status === PurchaseOrder::STATUS_GENERATED) return 'blue';
        if ($order->review_status === PurchaseOrder::REVIEW_APPROVED || $order->status === PurchaseOrder::STATUS_COMPLETED) return 'green';
        if ($order->review_status === PurchaseOrder::REVIEW_REJECTED || $order->inventory_edited_at) return 'red';
        return $activity === 'Sin abrir' ? 'amber' : 'blue';
    }

    private function sourceOrderChangeHistory(int $purchaseOrderId): array
    {
        return SystemAudit::query()
            ->where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', $purchaseOrderId)
            ->whereIn('action', ['inventory_edit', 'review'])
            ->latest('occurred_at')
            ->limit(20)
            ->get()
            ->map(fn (SystemAudit $audit) => [
                'id' => $audit->id,
                'user_name' => $audit->user_name,
                'occurred_at' => $audit->occurred_at,
                'summary' => $audit->observations ?: 'Se actualizó la solicitud.',
                'changes' => $audit->metadata['changes'] ?? [],
            ])
            ->all();
    }

    private function validateOrderPayload(Request $request): array
    {
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
            'items.*.purchase_presentation' => ['nullable', 'in:Pieza,Paquete,Caja,Kilo'],
            'items.*.package_quantity' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'decimal:0,3', 'min:0.001', 'max:9999.999'],
        ];

        return $request->validate($rules);
    }

    private function syncItems(PurchaseOrder $purchaseOrder, Branch $branch, array $items): void
    {
        $incomingBranchProductIds = collect($items)
            ->pluck('branch_product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $purchaseOrder->items()
            ->whereNotIn('branch_product_id', $incomingBranchProductIds)
            ->delete();

        foreach ($items as $item) {
            $branchProduct = BranchProduct::query()
                ->with('product')
                ->where('branch_id', $branch->id)
                ->findOrFail($item['branch_product_id']);

            $presentation = $this->normalizePurchasePresentation($branchProduct, $item);
            $requestedQuantity = $presentation['requested_quantity'];
            $estimatedPrice = (float) ($branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0);
            $purchasedQuantity = isset($item['purchased_quantity'])
                ? (float) $item['purchased_quantity']
                : null;
            $actualPrice = isset($item['actual_price'])
                ? (float) $item['actual_price']
                : null;
            $unavailable = (bool) ($item['unavailable'] ?? false);
            $discountAmount = $unavailable
                ? 0
                : max(0, (float) ($item['discount_amount'] ?? 0));
            $grossTotal = ($actualPrice === null || $purchasedQuantity === null)
                ? null
                : $actualPrice * $purchasedQuantity;

            if ($grossTotal !== null) {
                $discountAmount = min($discountAmount, $grossTotal);
            }

            $purchaseOrder->items()->updateOrCreate(
                ['branch_product_id' => $branchProduct->id],
                [
                    'product_id' => $branchProduct->product_id,
                    'current_stock' => $branchProduct->stock,
                    'min_stock' => $branchProduct->min_stock,
                    'requested_quantity' => $requestedQuantity,
                    'purchase_presentation' => $presentation['purchase_presentation'],
                    'package_quantity' => $presentation['package_quantity'],
                    'units_per_package' => $presentation['units_per_package'],
                    'purchased_quantity' => $unavailable ? 0 : $purchasedQuantity,
                    'estimated_price' => $estimatedPrice,
                    'estimated_total' => $estimatedPrice * $requestedQuantity,
                    'actual_price' => $unavailable ? 0 : $actualPrice,
                    'discount_amount' => $discountAmount,
                    'actual_total' => $unavailable
                        ? 0
                        : ($grossTotal === null ? null : $grossTotal - $discountAmount),
                    'status' => $this->resolveItemStatus(
                        null,
                        $purchasedQuantity,
                        $actualPrice,
                        $unavailable,
                        $discountAmount,
                        $requestedQuantity,
                        $estimatedPrice
                    ),
                ]
            );
        }
    }

    private function normalizePurchasePresentation(BranchProduct $branchProduct, array $item): array
    {
        $product = $branchProduct->product;
        $inventoryUnit = $product?->inventory_unit ?? $product?->unit ?? 'pza';
        $presentation = $item['purchase_presentation'] ?? ($inventoryUnit === 'kg' ? 'Kilo' : 'Pieza');

        if ($inventoryUnit === 'kg' && $presentation !== 'Kilo') {
            throw ValidationException::withMessages([
                'items' => "{$product?->name} debe solicitarse en kilogramos.",
            ]);
        }

        if ($inventoryUnit !== 'kg' && ! in_array($presentation, ['Pieza', 'Paquete', 'Caja'], true)) {
            throw ValidationException::withMessages([
                'items' => "{$product?->name} debe solicitarse en piezas, paquetes o cajas.",
            ]);
        }

        if ($presentation === 'Caja' && (! $product?->has_box_presentation || (int) $product?->pieces_per_box < 2)) {
            throw ValidationException::withMessages([
                'items' => "{$product?->name} no tiene compra/venta por caja configurada.",
            ]);
        }

        if ($presentation === 'Paquete' && (! $product?->has_package_presentation || (int) $product?->pieces_per_package < 2)) {
            throw ValidationException::withMessages([
                'items' => "{$product?->name} no tiene compra/venta por paquete configurada.",
            ]);
        }

        $packageQuantity = (float) ($item['package_quantity'] ?? $item['requested_quantity'] ?? 0);
        $unitsPerPackage = match ($presentation) {
            'Caja' => (int) $product?->pieces_per_box,
            'Paquete' => (int) $product?->pieces_per_package,
            default => 1,
        };

        if ($presentation !== 'Kilo' && abs($packageQuantity - round($packageQuantity)) > 0.0000001) {
            throw ValidationException::withMessages([
                'items' => 'Las piezas, paquetes y cajas deben solicitarse con números enteros.',
            ]);
        }

        $requestedQuantity = in_array($presentation, ['Caja', 'Paquete'], true)
            ? $packageQuantity * $unitsPerPackage
            : (float) ($item['requested_quantity'] ?? $packageQuantity);

        if ($presentation === 'Kilo') {
            $requestedQuantity = round($requestedQuantity, 3);
            $packageQuantity = $requestedQuantity;
        }

        return [
            'purchase_presentation' => $presentation,
            'package_quantity' => $packageQuantity,
            'units_per_package' => $unitsPerPackage,
            'requested_quantity' => $requestedQuantity,
        ];
    }

    private function resolveItemStatus(
        ?PurchaseOrderItem $item,
        ?float $purchasedQuantity,
        ?float $actualPrice,
        bool $unavailable,
        float $discountAmount = 0,
        ?float $requestedQuantity = null,
        ?float $estimatedPrice = null
    ): string {
        if ($unavailable) {
            return PurchaseOrderItem::STATUS_UNAVAILABLE;
        }

        $requestedQuantity ??= (float) ($item?->requested_quantity ?? 0);
        $estimatedPrice ??= (float) ($item?->estimated_price ?? 0);

        if ($purchasedQuantity === null && $actualPrice === null) {
            return PurchaseOrderItem::STATUS_REQUESTED;
        }

        if ($purchasedQuantity !== $requestedQuantity || $actualPrice !== $estimatedPrice || $discountAmount > 0) {
            return PurchaseOrderItem::STATUS_ADJUSTED;
        }

        return PurchaseOrderItem::STATUS_PURCHASED;
    }

    private function refreshTotals(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('items');

        $purchaseOrder->update([
            'estimated_total' => $purchaseOrder->items->sum(fn ($item) => (float) $item->estimated_total),
            'actual_total' => $purchaseOrder->items->sum(fn ($item) => (float) ($item->actual_total ?? 0)),
        ]);
    }

    private function makeGeneratedFolio(): string
    {
        $prefix = 'SC';
        $nextSequence = PurchaseOrder::query()
            ->where('status', '!=', PurchaseOrder::STATUS_DRAFT)
            ->where('folio', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->pluck('folio')
            ->map(function (?string $folio): int {
                if (! $folio || ! preg_match('/-(\d+)$/', $folio, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            })
            ->max();

        return sprintf('%s-%d', $prefix, max(1, $nextSequence + 1));
    }

    private function displayPurchaseFolio(?string $folio): ?string
    {
        if (! $folio) return null;
        if (preg_match('/^OC_[A-Za-z0-9]+_(\d+)$/', $folio, $matches)) return 'SC-'.$matches[1];
        return $folio;
    }

    private function statusLabel(string $status, ?string $reviewStatus = null, bool $edited = false): string
    {
        if ($status === PurchaseOrder::STATUS_GENERATED) {
            $label = match ($reviewStatus) {
                PurchaseOrder::REVIEW_APPROVED => 'Aprobada',
                PurchaseOrder::REVIEW_REJECTED => 'Rechazada',
                default => 'Pendiente',
            };
        } else {
            $label = match ($status) {
                PurchaseOrder::STATUS_DRAFT => 'Borrador',
                PurchaseOrder::STATUS_REVIEW => 'En revisión',
                PurchaseOrder::STATUS_COMPLETED => 'Completada',
                PurchaseOrder::STATUS_CANCELLED => 'Cancelada',
                default => $status,
            };
        }

        return $edited ? "{$label} · Editada" : $label;
    }

    private function reviewStatusLabel(?string $reviewStatus): string
    {
        return match ($reviewStatus) {
            PurchaseOrder::REVIEW_APPROVED => 'Aprobada',
            PurchaseOrder::REVIEW_REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }

    private function receiptStatusLabel(PurchaseOrderItem $item): string
    {
        if ($item->received_quantity === null) {
            return 'Pendiente de recepcion';
        }

        $received = (float) $item->received_quantity;
        $requested = (float) $item->requested_quantity;

        if ($received <= 0) {
            return 'No recibido';
        }

        return abs($received - $requested) < 0.001 ? 'Completo' : 'Incompleto';
    }
}
