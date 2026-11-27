<?php

namespace App\Http\Controllers\Inventory;

use App\Events\PurchaseWorkflowChanged;
use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseCycle;
use App\Models\PurchaseOrderTransfer;
use App\Models\SystemAudit;
use App\Models\User;
use App\Search\ProductSearchOptions;
use App\Search\ProductSearchService;
use App\Services\PurchaseCycleService;
use App\Services\PendingPurchaseOrderEditor;
use App\Services\SystemAuditService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class GeneralPurchaseOrderController extends Controller
{
    use AuthorizesBranchAccess;

    public function __construct(
        private readonly PurchaseCycleService $cycles,
        private readonly ProductSearchService $productSearch,
    ) {}

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $status = $request->input('status', 'GENERATE');

        if (! in_array($status, ['GENERATE', 'CENTRAL'], true)) {
            $status = 'GENERATE';
        }

        if (! $this->canViewStatus($request, $status)) {
            $status = collect(['GENERATE', 'CENTRAL'])
                ->first(fn (string $candidate) => $this->canViewStatus($request, $candidate));

            abort_unless($status, 403);
        }

        $payload = $status === 'GENERATE'
            ? $this->generationListPayload($request, $branch)
            : $this->listPayload($request, $branch, 'CENTRAL');

        return Inertia::render('Inventory/PurchaseOrders', array_merge($payload, [
            'notificationSummary' => $this->purchaseOrderNotificationSummary($request),
            'generation' => $this->canViewStatus($request, 'GENERATE')
                ? $this->generationPayload($request)
                : [],
        ]));
    }

    public function tracking(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return Inertia::render(
            'Inventory/PurchaseOrderTracking',
            $this->listPayload($request, $branch, GeneralPurchaseOrder::STATUS_PURCHASING)
        );
    }

    public function history(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return Inertia::render(
            'Inventory/PurchaseOrderHistory',
            $this->listPayload($request, $branch, GeneralPurchaseOrder::STATUS_COMPLETED)
        );
    }

    public function edit(Request $request, Branch $branch, GeneralPurchaseOrder $generalPurchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageGeneralOrder($request, $generalPurchaseOrder);
        abort_unless($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_PURCHASING, 404);

        $generalPurchaseOrder->load([
            'creator',
            'items.product:id,image,cost,description,unit,inventory_unit,pieces_per_box,pieces_per_package,packages_per_box,has_box_presentation,has_package_presentation,inventory_quantity_mode,cost_per_piece,cost_per_package,cost_per_box',
            'items.plannedBatches',
            'branchOrders.branch',
            'branchOrders.items',
        ]);

        return Inertia::render('Inventory/PurchaseOrderCapture', [
            'currentBranch' => $branch,
            // La distribución puede prepararse para cualquier sucursal activa,
            // aunque esa sucursal no haya solicitado este producto.
            'distributionBranches' => Branch::query()
                ->where('active', true)
                ->orderBy('is_central_warehouse')
                ->orderBy('name')
                ->get(['id', 'name', 'is_central_warehouse']),
            'centralWarehouse' => Branch::query()
                ->where('is_central_warehouse', true)
                ->where('active', true)
                ->first(['id', 'name']),
            'orderDB' => $this->orderPayload($generalPurchaseOrder),
        ]);
    }

    private function generationListPayload(Request $request, Branch $branch): array
    {
        $perPage = TablePagination::resolvePerPage($request);

        return [
            'currentBranch' => $branch,
            'ordersDB' => [
                'data' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $perPage,
                'total' => 0,
                'from' => null,
                'to' => null,
                'links' => [],
            ],
            'filters' => [
                'search' => trim((string) $request->input('search', '')),
                'status' => 'GENERATE',
                'source_status' => $request->input('source_status', 'ALL'),
                'per_page' => $perPage,
            ],
        ];
    }

    private function listPayload(Request $request, Branch $branch, string $status): array
    {

        $perPage = TablePagination::resolvePerPage($request);

        $centralStatus = $status === 'CENTRAL'
            ? $request->input('central_status', 'ALL')
            : $status;
        if (! in_array($centralStatus, ['ALL', GeneralPurchaseOrder::STATUS_PURCHASING, GeneralPurchaseOrder::STATUS_COMPLETED], true)) {
            $centralStatus = 'ALL';
        }

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => $status,
            'central_status' => $centralStatus,
            'per_page' => $perPage,
        ];

        $orders = GeneralPurchaseOrder::query()
            ->with(['branchOrders:id,general_purchase_order_id,branch_id', 'creator:id,name'])
            ->withCount('items')
            ->whereIn('status', [GeneralPurchaseOrder::STATUS_PURCHASING, GeneralPurchaseOrder::STATUS_COMPLETED])
            ->when($centralStatus !== 'ALL', fn ($query) => $query->where('status', $centralStatus));

        if ($filters['search'] !== '') {
            $matchingProductIds = $this->productSearch->ids(
                $filters['search'],
                new ProductSearchOptions(limit: (int) config('product_search.max_results', 10000)),
            );

            $orders->where(function ($searchQuery) use ($filters, $matchingProductIds) {
                if ($matchingProductIds->isEmpty()) {
                    $searchQuery->whereRaw('1 = 0');
                } else {
                    $searchQuery->whereHas('items', fn ($itemQuery) => $itemQuery
                        ->whereIntegerInRaw('product_id', $matchingProductIds->all()));
                }

                $searchQuery->orWhere(function ($historicalQuery) use ($filters) {
                    FlexibleSearch::applyAllTerms($historicalQuery, $filters['search'], function ($termQuery, $phrase, $terms) {
                        FlexibleSearch::orWhereColumns($termQuery, ['general_purchase_orders.folio'], $phrase, $terms);
                        FlexibleSearch::orWhereHasColumns($termQuery, 'items', ['product_name', 'product_code'], $phrase, $terms);
                        FlexibleSearch::orWhereHasColumns($termQuery, 'branchOrders.branch', ['name'], $phrase, $terms);
                    });
                });
            });
        }

        $orders = $orders
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (GeneralPurchaseOrder $order) => [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $order->status === GeneralPurchaseOrder::STATUS_COMPLETED
                    ? 'Completada'
                    : 'En compra',
                'items_count' => $order->items_count,
                'branches_count' => $order->branchOrders->pluck('branch_id')->unique()->count(),
                'display_date' => $order->completed_at ?? $order->created_at,
                'can_edit' => $this->canManageGeneralOrder($request, $order),
                'responsible_name' => $order->creator?->name ?? 'Sin información',
            ]);

        return [
            'currentBranch' => $branch,
            'ordersDB' => $orders,
            'filters' => $filters,
        ];
    }

    public function show(Request $request, Branch $branch, GeneralPurchaseOrder $generalPurchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $completed = $generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_COMPLETED;
        abort_unless($this->canViewStatus($request, $generalPurchaseOrder->status), 403);

        $generalPurchaseOrder->load([
            'creator',
            'items.product:id,image,cost,description,unit,inventory_unit,pieces_per_box,pieces_per_package,packages_per_box,has_box_presentation,has_package_presentation,inventory_quantity_mode,cost_per_piece,cost_per_package,cost_per_box',
            'items.plannedBatches',
            'branchOrders.branch',
            'branchOrders.items',
        ]);

        $canViewCosts = $request->user()->hasPermission('inventory.purchase-orders.general.update');

        return response()->json($this->orderPayload(
            $generalPurchaseOrder,
            $completed && $canViewCosts,
        ));
    }

    public function consolidate(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $payload = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'distinct'],
        ]);

        $order = DB::transaction(function () use ($request, $payload) {
            $preparation = DB::table('purchase_preparations')->where('id', 1)->lockForUpdate()->first();
            abort_unless($preparation && (int) $preparation->responsible_id === $request->user()->id, 403, 'Sólo la responsable puede crear la compra central.');
            $savedIds = collect(json_decode($preparation->order_ids ?? '[]', true))->map(fn ($id) => (int) $id)->sort()->values();
            $submittedIds = collect($payload['order_ids'])->map(fn ($id) => (int) $id)->sort()->values();
            abort_unless($savedIds->isNotEmpty() && $savedIds->all() === $submittedIds->all(), 422, 'La selección cambió. Actualiza antes de crear la compra.');
            $cycle = $this->cycles->currentOpenCycle($request->user());
            $order = $this->cycles->consolidate($cycle, $request->user(), $savedIds->all());
            DB::table('purchase_preparations')->where('id', 1)->update(['responsible_id' => null, 'order_ids' => '[]', 'updated_at' => now()]);
            return $order;
        });
        PurchaseWorkflowChanged::dispatch('central_purchase_created');

        return redirect()->back()->with(
            'success',
            $order
                ? 'Compra central creada correctamente.'
                : 'No había productos por incluir en la Compra central.'
        );
    }

    /**
     * Devuelve una compra central sin captura a la selección compartida.
     * No borra ni altera mercancía: sólo es posible antes de registrar lotes,
     * cantidades compradas o una revisión física.
     */
    public function reopen(Request $request, Branch $branch, GeneralPurchaseOrder $generalPurchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageGeneralOrder($request, $generalPurchaseOrder);

        $snapshot = $this->restoreCentralPurchase($request, $generalPurchaseOrder, true);

        app(SystemAuditService::class)->record('purchase-orders', 'central_reopened', 'success', $request, [
            'record_type' => GeneralPurchaseOrder::class,
            'record_id' => $snapshot['general_purchase_order']['id'],
            'record_label' => $snapshot['general_purchase_order']['folio'],
            'observations' => 'La compra central se regresó a edición; se conservó una fotografía previa.',
            'metadata' => ['snapshot' => $snapshot],
        ]);

        PurchaseWorkflowChanged::dispatch('central_purchase_reopened');

        return redirect()->route('inventory.branches.reports.purchase-orders', [
            'branch' => $branch->id,
            'status' => 'GENERATE',
        ])->with('success', 'Compra central regresada a edición. Puedes agregar o quitar solicitudes antes de crearla nuevamente.');
    }

    /** Descarta la compra central y devuelve las solicitudes a Pendiente. */
    public function discard(Request $request, Branch $branch, GeneralPurchaseOrder $generalPurchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageGeneralOrder($request, $generalPurchaseOrder);

        $snapshot = $this->restoreCentralPurchase($request, $generalPurchaseOrder, false);

        app(SystemAuditService::class)->record('purchase-orders', 'central_discarded', 'success', $request, [
            'record_type' => GeneralPurchaseOrder::class,
            'record_id' => $snapshot['general_purchase_order']['id'],
            'record_label' => $snapshot['general_purchase_order']['folio'],
            'observations' => 'La compra central se descartó y las solicitudes regresaron a Pendiente; se conservó una fotografía previa.',
            'metadata' => ['snapshot' => $snapshot],
        ]);

        PurchaseWorkflowChanged::dispatch('central_purchase_discarded');

        return redirect()->route('inventory.branches.reports.purchase-orders', [
            'branch' => $branch->id,
            'status' => 'GENERATE',
        ])->with('success', 'Compra central descartada. Las solicitudes regresaron a Pendientes.');
    }

    public function sourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->id, 404);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);

        $purchaseOrder->load([
            'branch',
            'user',
            'assignedTo',
            'reviewedBy',
            'transfers.fromUser',
            'transfers.toUser',
            'transfers.transferredBy',
            'items.branchProduct.product.barcodes',
            'items.branchProduct.product.category',
        ]);

        return response()->json($this->sourceOrderPayload($purchaseOrder));
    }

    public function transferSourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_GENERATED, 422);
        abort_unless(
            $purchaseOrder->review_status === PurchaseOrder::REVIEW_APPROVED,
            422,
            'La orden debe estar aprobada antes de transferirse.'
        );
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

        $payload = $request->validate([
            'assigned_to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $targetUser = User::query()
            ->whereKey($payload['assigned_to_user_id'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Inventario'))
            ->first();

        if (! $targetUser || ! $targetUser->hasBranchAccess((int) $purchaseOrder->branch_id)) {
            return back()->withErrors([
                'assigned_to_user_id' => 'La persona seleccionada no pertenece a Inventario o no tiene acceso a la sucursal.',
            ]);
        }

        if ((int) $targetUser->id === (int) $purchaseOrder->assigned_to_user_id) {
            return back()->withErrors([
                'assigned_to_user_id' => 'Selecciona a otra persona de Inventario.',
            ]);
        }

        DB::transaction(function () use ($purchaseOrder, $request, $targetUser) {
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);

            abort_if($lockedOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');
            abort_unless(
                $lockedOrder->review_status === PurchaseOrder::REVIEW_APPROVED,
                422,
                'La orden debe estar aprobada antes de transferirse.'
            );

            PurchaseOrderTransfer::create([
                'purchase_order_id' => $lockedOrder->id,
                'from_user_id' => $lockedOrder->assigned_to_user_id,
                'to_user_id' => $targetUser->id,
                'transferred_by' => $request->user()?->id,
            ]);

            $lockedOrder->update(['assigned_to_user_id' => $targetUser->id]);
        });
        $purchaseOrder->loadMissing('branch');
        $transferredBy = $request->user()?->name ?? 'Sistema';

        event(new RealtimeActivityLogged(
            "Recibiste la Orden de compra {$purchaseOrder->folio} de {$purchaseOrder->branch?->name}, transferida por {$transferredBy}.",
            'Inventario',
            'purchase_order_transferred',
            $purchaseOrder->folio,
            [$targetUser->id],
            false,
        ));

        return redirect()->back()->with('success', 'Orden transferida correctamente.');
    }

    public function reviewSourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->id, 404);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

        $payload = $request->validate([
            'review_status' => [
                'required',
                Rule::in([
                    PurchaseOrder::REVIEW_PENDING,
                    PurchaseOrder::REVIEW_APPROVED,
                    PurchaseOrder::REVIEW_REJECTED,
                ]),
            ],
        ]);

        DB::transaction(function () use ($purchaseOrder, $payload, $request) {
            DB::table('purchase_preparations')->insertOrIgnore(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $preparation = DB::table('purchase_preparations')->where('id', 1)->lockForUpdate()->first();
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_if($lockedOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

            $selectedIds = collect(json_decode($preparation->order_ids ?? '[]', true))
                ->map(fn ($id) => (int) $id);
            if ($payload['review_status'] === PurchaseOrder::REVIEW_APPROVED) {
                abort_unless($preparation->responsible_id, 422, 'Primero debe asumir la compra una responsable.');
                $responsible = User::query()->findOrFail($preparation->responsible_id);
                abort_unless($responsible->hasBranchAccess($lockedOrder->branch_id), 422, 'La responsable elegida no tiene acceso a esta sucursal.');
                $selectedIds->push($lockedOrder->id);
            } else {
                $selectedIds = $selectedIds->reject(fn (int $id) => $id === (int) $lockedOrder->id);
            }

            $lockedOrder->update([
                'review_status' => $payload['review_status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
            DB::table('purchase_preparations')->where('id', 1)->update([
                'order_ids' => $selectedIds->unique()->values()->toJson(),
                'updated_at' => now(),
            ]);
        }, 3);
        $purchaseOrder->refresh();

        $label = match ($payload['review_status']) {
            PurchaseOrder::REVIEW_APPROVED => 'aprobada',
            PurchaseOrder::REVIEW_REJECTED => 'rechazada',
            default => 'regresada a revisión',
        };

        app(SystemAuditService::class)->record('purchase-orders', 'review', 'success', $request, [
            'record_type' => PurchaseOrder::class,
            'record_id' => $purchaseOrder->id,
            'record_label' => $purchaseOrder->folio,
            'observations' => "La Orden de compra fue {$label}.",
        ]);

        if ($purchaseOrder->user_id && (int) $purchaseOrder->user_id !== (int) $request->user()?->id) {
            event(new RealtimeActivityLogged(
                "La Orden de compra {$purchaseOrder->folio} fue {$label}.",
                'Inventario',
                'purchase_order_reviewed',
                $purchaseOrder->folio,
                [$purchaseOrder->user_id],
                false,
            ));
        }

        PurchaseWorkflowChanged::dispatch('request_reviewed', $purchaseOrder->id);

        return redirect()->back()->with('success', "Orden {$label} correctamente.");
    }

    public function destroySourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->id, 404);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_unless($purchaseOrder->review_status === PurchaseOrder::REVIEW_REJECTED, 422, 'Sólo se pueden eliminar solicitudes rechazadas.');
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La solicitud ya forma parte de una compra central.');

        DB::transaction(function () use ($purchaseOrder) {
            $preparation = DB::table('purchase_preparations')->where('id', 1)->lockForUpdate()->first();
            if ($preparation) {
                $ids = collect(json_decode($preparation->order_ids ?? '[]', true))
                    ->reject(fn ($id) => (int) $id === (int) $purchaseOrder->id)
                    ->values();
                DB::table('purchase_preparations')->where('id', 1)->update(['order_ids' => $ids->toJson(), 'updated_at' => now()]);
            }
            $purchaseOrder->delete();
        });

        app(SystemAuditService::class)->record('purchase-orders', 'delete', 'success', $request, [
            'record_type' => PurchaseOrder::class,
            'record_id' => $purchaseOrder->id,
            'record_label' => $purchaseOrder->folio,
            'observations' => 'Se eliminó una solicitud de compra rechazada.',
        ]);
        PurchaseWorkflowChanged::dispatch('request_deleted', $purchaseOrder->id);

        return redirect()->back()->with('success', 'Solicitud rechazada eliminada correctamente.');
    }

    public function updateSourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        abort_unless((int) $purchaseOrder->branch_id === (int) $branch->id, 404);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

        $purchaseOrder->loadMissing('branch', 'items.branchProduct.product');
        $beforeItems = $this->sourceOrderItemSnapshot($purchaseOrder);
        $editor = app(PendingPurchaseOrderEditor::class);
        $validated = $request->validate($editor->rules());
        $updatedOrder = $editor->update(
            $purchaseOrder,
            $purchaseOrder->branch,
            $validated['items'],
            $request->user(),
            true,
            $validated['record_version'],
        );
        $changes = $this->sourceOrderChangeSummary($beforeItems, $this->sourceOrderItemSnapshot($updatedOrder));

        app(SystemAuditService::class)->record('purchase-orders', 'inventory_edit', 'success', $request, [
            'record_type' => PurchaseOrder::class,
            'record_id' => $updatedOrder->id,
            'record_label' => $updatedOrder->folio,
            'observations' => 'Inventario modificó una Orden de compra pendiente antes de integrarla a una Orden de compra general.',
            'metadata' => ['changes' => $changes],
        ]);

        if ($updatedOrder->user_id) {
            event(new RealtimeActivityLogged(
                "Inventario editó la Orden de compra {$updatedOrder->folio} de {$updatedOrder->branch?->name}.",
                'Inventario',
                'purchase_order_edited',
                $updatedOrder->folio,
                [$updatedOrder->user_id],
                false,
            ));
        }

        PurchaseWorkflowChanged::dispatch('request_edited', $updatedOrder->id);

        return redirect()->back()->with('success', 'Orden de compra actualizada y notificada a Ventas.');
    }

    public function update(
        Request $request,
        Branch $branch,
        GeneralPurchaseOrder $generalPurchaseOrder
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageGeneralOrder($request, $generalPurchaseOrder);
        abort_unless($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_PURCHASING, 404);

        $this->cycles->saveCapture($generalPurchaseOrder, $this->validatedPayload($request));

        return redirect()->back()->with('success', 'Orden general actualizada correctamente.');
    }

    public function complete(
        Request $request,
        Branch $branch,
        GeneralPurchaseOrder $generalPurchaseOrder
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageGeneralOrder(
            $request,
            $generalPurchaseOrder,
            'inventory.purchase-orders.general.complete'
        );

        if ($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_COMPLETED) {
            return $this->completedOrdersRedirect($branch)
                ->with('success', 'La compra general ya estaba completada.');
        }

        abort_unless($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_PURCHASING, 422);

        $completedOrder = $this->cycles->complete(
            $generalPurchaseOrder,
            $this->validatedPayload($request),
            $request->user()
        );

        $completedOrder->load('branchOrders.branch');

        foreach ($completedOrder->branchOrders as $branchOrder) {
            if (! $branchOrder->user_id) {
                continue;
            }

            event(new RealtimeActivityLogged(
                "La orden {$branchOrder->folio} de {$branchOrder->branch?->name} ya esta por revisar.",
                'Ventas',
                'review_requested',
                $branchOrder->folio,
                [$branchOrder->user_id],
                false,
            ));
        }

        return $this->completedOrdersRedirect($branch)
            ->with(
                'success',
                'Compra general completada y órdenes de sucursal enviadas a revisión.'
            );
    }

    private function completedOrdersRedirect(Branch $branch)
    {
        return redirect()->route('inventory.branches.reports.purchase-orders', [
            'branch' => $branch->id,
            'status' => GeneralPurchaseOrder::STATUS_COMPLETED,
        ]);
    }

    /**
     * Guarda una fotografía completa de la central y restaura sus solicitudes.
     * En edición se mantienen aprobadas y seleccionadas; al descartar vuelven a
     * Pendiente para que Inventario las revise de nuevo.
     */
    private function restoreCentralPurchase(
        Request $request,
        GeneralPurchaseOrder $generalPurchaseOrder,
        bool $returnToEdition,
    ): array {
        return DB::transaction(function () use ($request, $generalPurchaseOrder, $returnToEdition) {
            $order = GeneralPurchaseOrder::query()
                ->with(['items.plannedBatches', 'branchOrders.items'])
                ->lockForUpdate()
                ->findOrFail($generalPurchaseOrder->id);

            if ($order->status !== GeneralPurchaseOrder::STATUS_PURCHASING) {
                throw ValidationException::withMessages([
                    'order' => 'Sólo se puede deshacer una compra que aún está en compra.',
                ]);
            }

            $hasCapture = $order->items->contains(fn (GeneralPurchaseOrderItem $item) =>
                $item->physically_reviewed
                || $item->plannedBatches->isNotEmpty()
                || (float) $item->purchased_quantity > 0
                || (bool) $item->unavailable
            );
            if ($hasCapture) {
                throw ValidationException::withMessages([
                    'order' => 'Esta compra ya tiene captura. No se puede deshacer para proteger la mercancía registrada.',
                ]);
            }

            $sourceIds = $order->branchOrders->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            if (! $sourceIds) {
                throw ValidationException::withMessages([
                    'order' => 'La compra central ya no tiene solicitudes para restaurar.',
                ]);
            }

            $snapshot = [
                'captured_at' => now()->toJSON(),
                'general_purchase_order' => $order->attributesToArray(),
                'items' => $order->items->map(fn (GeneralPurchaseOrderItem $item) => [
                    'item' => $item->attributesToArray(),
                    'planned_batches' => $item->plannedBatches->map->attributesToArray()->all(),
                ])->all(),
                'source_orders' => $order->branchOrders->map(fn (PurchaseOrder $sourceOrder) => [
                    'order' => $sourceOrder->attributesToArray(),
                    'items' => $sourceOrder->items->map->attributesToArray()->all(),
                ])->all(),
            ];

            $cycle = PurchaseCycle::query()->lockForUpdate()->find($order->purchase_cycle_id);
            if ($cycle && $cycle->status === PurchaseCycle::STATUS_CONSOLIDATED) {
                $cycle->update([
                    'status' => PurchaseCycle::STATUS_OPEN,
                    'consolidated_at' => null,
                ]);
            }

            $sourceQuery = PurchaseOrder::query()->whereIn('id', $sourceIds);
            $sourceQuery->update(['general_purchase_order_id' => null]);

            $preparation = DB::table('purchase_preparations')->where('id', 1)->lockForUpdate()->first();
            $preparationPayload = [
                'responsible_id' => $returnToEdition ? $request->user()->id : null,
                'order_ids' => $returnToEdition ? json_encode($sourceIds) : '[]',
                'updated_at' => now(),
            ];
            if ($preparation) {
                DB::table('purchase_preparations')->where('id', 1)->update($preparationPayload);
            } else {
                DB::table('purchase_preparations')->insert($preparationPayload + ['id' => 1, 'created_at' => now()]);
            }

            if (! $returnToEdition) {
                $sourceQuery->update([
                    'review_status' => PurchaseOrder::REVIEW_PENDING,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'inventory_edited_by' => null,
                    'inventory_edited_at' => null,
                ]);
            }

            $order->delete();

            return $snapshot;
        });
    }

    private function abortIfCannotManageGeneralOrder(
        Request $request,
        GeneralPurchaseOrder $generalPurchaseOrder,
        string $permission = 'inventory.purchase-orders.general.update'
    ): void
    {
        abort_unless(
            $this->canManageGeneralOrder($request, $generalPurchaseOrder, $permission),
            403,
            'Solo la persona responsable o un administrador autorizado puede editar esta Orden de compra general.'
        );
    }

    private function canManageGeneralOrder(
        Request $request,
        GeneralPurchaseOrder $generalPurchaseOrder,
        string $permission = 'inventory.purchase-orders.general.update'
    ): bool {
        $user = $request->user();

        if (! $user?->hasPermission($permission)) {
            return false;
        }

        $user->loadMissing('role');

        return (int) $generalPurchaseOrder->created_by === (int) $user->id
            || in_array($user->role?->name, ['Administrador', 'Super Administrador'], true);
    }

    private function canViewStatus(Request $request, string $status): bool
    {
        if ($status === 'CENTRAL') {
            return $request->user()?->hasPermission('inventory.purchase-orders.general.view') ?? false;
        }

        $permission = match ($status) {
            'GENERATE' => 'inventory.purchase-orders.source.view',
            GeneralPurchaseOrder::STATUS_PURCHASING => 'inventory.purchase-orders.general.view',
            GeneralPurchaseOrder::STATUS_COMPLETED => 'inventory.purchase-orders.general.view',
            default => null,
        };

        return $permission && $request->user()?->hasPermission($permission);
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'purchased_at' => ['required', 'date'],
            'record_version' => ['required', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:general_purchase_order_items,id'],
            'items.*.purchase_presentation' => ['required', 'in:Pieza,Paquete,Caja,Kilo,Gramo'],
            'items.*.package_quantity' => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:9999.999'],
            'items.*.units_per_package' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'items.*.purchase_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'items.*.purchase_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.unavailable' => ['nullable', 'boolean'],
            'items.*.batches' => ['nullable', 'array', 'max:50'],
            'items.*.branch_allocations' => ['nullable', 'array'],
            'items.*.physically_reviewed' => ['sometimes', 'boolean'],
            'items.*.branch_allocations.*.branch_id' => ['required', 'integer'],
            'items.*.branch_allocations.*.quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.batches.*.lot_number' => ['nullable', 'string', 'max:100'],
            'items.*.batches.*.supplier' => ['nullable', 'string', 'max:80'],
            'items.*.batches.*.allocations' => ['nullable', 'array'],
            'items.*.batches.*.allocations.*.branch_id' => ['required', 'integer'],
            'items.*.batches.*.allocations.*.quantity' => ['required', 'numeric', 'min:0', 'decimal:0,3'],
            'items.*.batches.*.expiration_date' => ['nullable', 'date'],
            'items.*.batches.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0.001', 'max:999999.999'],
        ]);
    }

    private function generationPayload(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('role');
        $accessibleBranchIds = Branch::query()->where('active', true)->pluck('id');
        $orders = PurchaseOrder::query()
            ->with(['branch', 'user', 'assignedTo', 'reviewedBy'])
            ->withCount('items')
            ->withCount('transfers')
            ->withSum('items as requested_quantity', 'requested_quantity')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', PurchaseOrder::STATUS_GENERATED)
            ->whereNull('general_purchase_order_id')
            ->oldest('generated_at')
            ->oldest('id')
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'folio' => $order->folio,
                'branch_id' => $order->branch_id,
                'branch_name' => $order->branch?->name ?? 'Sucursal',
                'assigned_to_user_id' => $order->assigned_to_user_id,
                'assigned_to_name' => $order->assignedTo?->name ?? 'Sin responsable',
                'requested_by_name' => $order->user?->name ?? 'Sin información',
                'review_status' => $order->review_status,
                'review_status_label' => $this->reviewStatusLabel($order->review_status),
                'reviewed_by_name' => $order->reviewedBy?->name,
                'reviewed_at' => $order->reviewed_at,
                'transfer_history_count' => $order->transfers_count,
                'items_count' => $order->items_count,
                'requested_quantity' => (float) ($order->requested_quantity ?? 0),
                'generated_at' => $order->generated_at ?? $order->created_at,
            ]);
        $preparation = DB::table('purchase_preparations')->where('id', 1)->first();
        $selectedIds = json_decode($preparation?->order_ids ?? '[]', true);
        $presence = DB::table('purchase_review_presence')->join('users', 'users.id', '=', 'purchase_review_presence.user_id')
            ->whereIn('purchase_order_id', $orders->pluck('id'))->get()->groupBy('purchase_order_id');
        $orders = $orders->map(function ($order) use ($selectedIds, $presence) {
            $visits = $presence->get($order['id'], collect());
            $names = $visits->filter(fn ($visit) => $visit->last_seen_at && \Carbon\Carbon::parse($visit->last_seen_at)->gt(now()->subSeconds(75)))->pluck('name')->unique()->implode(', ');
            $order['selected'] = in_array($order['id'], $selectedIds);
            $order['review_activity'] = $names ? 'Abierta por: '.$names : ($visits->isNotEmpty() ? 'En revisión' : 'Sin abrir');
            return $order;
        });
        $orderCounts = $orders->countBy(fn ($order) => (int) $order['branch_id']);

        $branches = Branch::query()
            ->whereIn('id', $accessibleBranchIds)
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'orders_count' => (int) $orderCounts->get((int) $branch->id, 0),
            ])
            ->values();

        $inventoryUsers = User::query()
            ->with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Inventario'))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $inventoryUser) => $inventoryUser->hasPermission('inventory.purchase-orders.general.create'))
            ->map(fn (User $inventoryUser) => [
                'id' => $inventoryUser->id,
                'name' => $inventoryUser->name,
                'branch_ids' => $inventoryUser->accessibleBranchIds(),
            ])
            ->values();

        $activeResponsible = $preparation?->responsible_id
            ? User::query()->whereKey($preparation->responsible_id)->where('is_active', true)->first()
            : null;

        return [
            'branches' => $branches,
            'orders' => $orders->values(),
            'inventory_users' => $inventoryUsers,
            'draft' => null,
            'preparation' => [
                'responsible_id' => $activeResponsible?->id,
                'responsible_name' => $activeResponsible?->name,
                'order_ids' => $selectedIds,
            ],
        ];
    }

    private function purchaseOrderNotificationSummary(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('role');

        $accessibleBranchIds = $user->accessibleBranchIds();
        $items = PurchaseOrder::query()
            ->with(['branch', 'user', 'assignedTo'])
            ->withCount('items')
            ->withCount('transfers')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', PurchaseOrder::STATUS_GENERATED)
            ->whereNull('general_purchase_order_id')
            ->latest('generated_at')
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'folio' => $order->folio,
                'title' => $order->folio,
                'description' => ($order->branch?->name ?? 'Sucursal').' · '.$order->items_count.' producto(s)',
                'meta' => 'Solicitada por '.($order->user?->name ?? 'Sin informacion'),
                'badge' => (int) $order->transfers_count > 0 ? 'Transferida' : 'Asignada',
                'tone' => (int) $order->transfers_count > 0 ? 'blue' : 'amber',
                'status' => $order->status,
                'updated_at' => $order->updated_at,
            ]);

        return [
            'mode' => 'inventory',
            'count' => $items->count(),
            'items' => $items,
        ];
    }

    private function sourceOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        $activity = $this->sourceOrderReviewActivity($purchaseOrder->id);

        return [
            'id' => $purchaseOrder->id,
            'folio' => $purchaseOrder->folio,
            'status' => $purchaseOrder->status,
            'record_version' => $purchaseOrder->updated_at?->toJSON(),
            'status_label' => $this->sourceOrderWorkflowLabel($purchaseOrder, $activity),
            'workflow_status_label' => $this->sourceOrderWorkflowLabel($purchaseOrder, $activity),
            'review_status' => $purchaseOrder->review_status,
            'review_status_label' => $this->reviewStatusLabel($purchaseOrder->review_status),
            'review_activity' => $activity,
            'change_history' => $this->sourceOrderChangeHistory($purchaseOrder->id),
            'requested_at' => $purchaseOrder->generated_at ?? $purchaseOrder->created_at,
            'items_count' => $purchaseOrder->items->count(),
            'branch' => [
                'id' => $purchaseOrder->branch_id,
                'name' => $purchaseOrder->branch?->name ?? 'Sucursal',
            ],
            'assigned_to' => [
                'id' => $purchaseOrder->assigned_to_user_id,
                'name' => $purchaseOrder->assignedTo?->name ?? 'Sin responsable',
            ],
            'user' => [
                'id' => $purchaseOrder->user_id,
                'name' => $purchaseOrder->user?->name ?? 'Sin información',
            ],
            'requested_by_name' => $purchaseOrder->user?->name ?? 'Sin información',
            'reviewed_by_name' => $purchaseOrder->reviewedBy?->name,
            'reviewed_at' => $purchaseOrder->reviewed_at,
            'transfers' => $purchaseOrder->transfers
                ->sortByDesc('created_at')
                ->map(fn (PurchaseOrderTransfer $transfer) => [
                    'id' => $transfer->id,
                    'from_user_name' => $transfer->fromUser?->name ?? 'Sin responsable previo',
                    'to_user_name' => $transfer->toUser?->name ?? 'Usuario no disponible',
                    'transferred_by_name' => $transfer->transferredBy?->name ?? 'Sistema',
                    'transferred_at' => $transfer->created_at,
                ])
                ->values(),
            'items' => $purchaseOrder->items->map(function (PurchaseOrderItem $item) {
                $branchProduct = $item->branchProduct;
                $product = $branchProduct?->product;

                return [
                    'id' => $item->id,
                    'branch_product_id' => $item->branch_product_id,
                    'product_name' => $product?->name ?? 'Producto sin nombre',
                    'product_code' => $product?->barcodes?->first()?->code ?: ($branchProduct?->barcode ?? ''),
                    'category_name' => $product?->category?->name ?? 'Sin categoría',
                    'base_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
                    'inventory_unit' => $product?->inventory_unit ?? $product?->unit ?? 'pza',
                    'purchase_presentation' => $item->purchase_presentation ?? 'Pieza',
                    'package_quantity' => (float) ($item->package_quantity ?? $item->requested_quantity),
                    'units_per_package' => (float) ($item->units_per_package ?? 1),
                    'requested_quantity' => (float) $item->requested_quantity,
                    'received_quantity' => null,
                    'receipt_notes' => null,
                    'status' => $item->status,
                    'status_label' => 'Pendiente de recepción',
                ];
            })->values(),
        ];
    }

    private function abortIfCannotManageSourceOrder(Request $request, PurchaseOrder $purchaseOrder): void
    {
        $user = $request->user();
        $user->loadMissing('role');

        abort_unless($purchaseOrder->status === PurchaseOrder::STATUS_GENERATED, 404);
        abort_unless($user->hasPermission('inventory.purchase-orders.source.view'), 403);
    }

    private function orderPayload(GeneralPurchaseOrder $order, bool $includePurchaseData = true): array
    {
        $payload = [
            'id' => $order->id,
            'folio' => $order->folio,
            'status' => $order->status,
            'status_label' => $order->status === GeneralPurchaseOrder::STATUS_COMPLETED
                ? 'Completada'
                : ($order->status === GeneralPurchaseOrder::STATUS_PURCHASING ? 'En compra' : 'Borrador'),
            'created_at' => $order->created_at,
            'completed_at' => $order->completed_at,
            'record_version' => $order->updated_at?->toJSON(),
            'created_by' => [
                'id' => $order->created_by,
                'name' => $order->creator?->name ?? 'Sin información',
            ],
            'branches' => $order->branchOrders->map(fn ($branchOrder) => [
                'id' => $branchOrder->branch_id,
                'order_id' => $branchOrder->id,
                'name' => $branchOrder->branch?->name ?? 'Sucursal',
                'folio' => $branchOrder->folio,
                'requested_quantity' => (float) $branchOrder->items->sum('requested_quantity'),
                'products_count' => $branchOrder->items->count(),
            ])->values(),
            'items' => $order->items->map(function (GeneralPurchaseOrderItem $item) use ($order, $includePurchaseData) {
                $breakdown = $order->branchOrders
                    ->map(function ($branchOrder) use ($item) {
                        $branchItem = $branchOrder->items->firstWhere('product_id', $item->product_id);

                        if (! $branchItem) {
                            return null;
                        }

                        return [
                            'branch_id' => $branchOrder->branch_id,
                            'branch_name' => $branchOrder->branch?->name ?? 'Sucursal',
                            'requested_quantity' => (float) $branchItem->requested_quantity,
                        ];
                    })
                    ->filter()
                    ->groupBy('branch_id')
                    ->map(fn ($branchItems) => [
                        'branch_id' => $branchItems->first()['branch_id'],
                        'branch_name' => $branchItems->first()['branch_name'],
                        'requested_quantity' => (float) $branchItems->sum('requested_quantity'),
                    ])
                    ->values();

                $itemPayload = $includePurchaseData
                    ? $item->attributesToArray()
                    : $item->only([
                        'id',
                        'product_id',
                        'product_name',
                        'product_code',
                        'base_unit',
                        'requested_quantity',
                    ]);

                return array_merge($itemPayload, [
                    'base_unit' => $item->product?->inventory_unit
                        ?? $item->product?->unit
                        ?? $item->base_unit,
                    'has_box_presentation' => (bool) $item->product?->has_box_presentation,
                    'has_package_presentation' => (bool) $item->product?->has_package_presentation,
                    'product_pieces_per_box' => (int) ($item->product?->pieces_per_box ?? 0),
                    'product_pieces_per_package' => (int) ($item->product?->pieces_per_package ?? 0),
                    'product_packages_per_box' => (int) ($item->product?->packages_per_box ?? 0),
                    'branch_breakdown' => $breakdown,
                    'image_url' => $item->product?->image
                        ? route('inventory.products.image', ['product' => $item->product_id])
                        : null,
                    'product_description' => $item->product_description
                        ?: $item->product?->description,
                    'previous_cost' => (float) ($item->estimated_unit_price ?? $item->product?->cost_per_piece ?? $item->product?->cost ?? 0),
                    'batches' => $item->plannedBatches->map(fn ($batch) => [
                        'id' => $batch->id,
                        'allocations' => $batch->allocations,
                        'supplier' => $batch->supplier,
                        'lot_number' => $batch->lot_number,
                        'expiration_date' => $batch->expiration_date?->format('Y-m-d'),
                        'quantity' => (float) $batch->presentation_quantity,
                        'base_quantity' => (float) $batch->base_quantity,
                    ])->values(),
                ]);
            })->values(),
        ];

        if (! $includePurchaseData) {
            return $payload;
        }

        return array_merge($payload, [
            'purchased_at' => optional($order->purchased_at)->format('Y-m-d'),
        ]);
    }

    private function reviewStatusLabel(?string $status): string
    {
        return match ($status) {
            PurchaseOrder::REVIEW_APPROVED => 'Aprobada',
            PurchaseOrder::REVIEW_REJECTED => 'Rechazada',
            default => 'Pendiente',
        };
    }

    private function sourceOrderReviewActivity(int $purchaseOrderId): string
    {
        $visits = DB::table('purchase_review_presence')
            ->join('users', 'users.id', '=', 'purchase_review_presence.user_id')
            ->where('purchase_order_id', $purchaseOrderId)
            ->get(['users.name', 'last_seen_at']);
        $names = $visits->filter(fn ($visit) => $visit->last_seen_at && now()->diffInSeconds($visit->last_seen_at) <= 75)->pluck('name')->unique()->implode(', ');

        return $names !== '' ? 'Abierta por: '.$names : ($visits->isNotEmpty() ? 'En revisión' : 'Sin abrir');
    }

    private function sourceOrderWorkflowLabel(PurchaseOrder $order, string $activity): string
    {
        if ($order->review_status === PurchaseOrder::REVIEW_APPROVED) return 'Aprobada';
        if ($order->review_status === PurchaseOrder::REVIEW_REJECTED) return 'Rechazada';
        if ($order->inventory_edited_at) return 'Modificada';

        return $activity === 'Sin abrir' ? 'Pendiente' : $activity;
    }

    private function sourceOrderItemSnapshot(PurchaseOrder $order): array
    {
        return $order->items->mapWithKeys(fn (PurchaseOrderItem $item) => [
            $item->branch_product_id => [
                'name' => $item->branchProduct?->product?->name ?? 'Producto',
                'quantity' => (float) $item->requested_quantity,
            ],
        ])->all();
    }

    private function sourceOrderChangeSummary(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $id => $item) {
            if (! isset($before[$id])) $changes[] = "Agregó {$item['name']}: {$item['quantity']}";
            elseif (abs($before[$id]['quantity'] - $item['quantity']) >= 0.001) $changes[] = "{$item['name']}: {$before[$id]['quantity']} → {$item['quantity']}";
        }
        foreach ($before as $id => $item) {
            if (! isset($after[$id])) $changes[] = "Quitó {$item['name']}";
        }

        return $changes;
    }

    private function sourceOrderChangeHistory(int $purchaseOrderId): array
    {
        return SystemAudit::query()
            ->where('auditable_type', PurchaseOrder::class)
            ->where('auditable_id', $purchaseOrderId)
            ->whereIn('action', ['inventory_edit', 'review'])
            ->latest('occurred_at')->limit(20)->get()
            ->map(fn (SystemAudit $audit) => [
                'id' => $audit->id,
                'user_name' => $audit->user_name,
                'occurred_at' => $audit->occurred_at,
                'summary' => $audit->observations ?: 'Se actualizó la solicitud.',
                'changes' => $audit->metadata['changes'] ?? [],
            ])->all();
    }
}
