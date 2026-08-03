<?php

namespace App\Http\Controllers\Inventory;

use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderTransfer;
use App\Models\User;
use App\Services\PurchaseCycleService;
use App\Services\PendingPurchaseOrderEditor;
use App\Services\SystemAuditService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class GeneralPurchaseOrderController extends Controller
{
    use AuthorizesBranchAccess;

    public function __construct(private readonly PurchaseCycleService $cycles) {}

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $cycle = $this->cycles->currentOpenCycle($request->user());
        $status = $request->input('status', 'GENERATE');

        if (! in_array($status, ['GENERATE', GeneralPurchaseOrder::STATUS_PURCHASING, GeneralPurchaseOrder::STATUS_COMPLETED], true)) {
            $status = 'GENERATE';
        }

        if (! $this->canViewStatus($request, $status)) {
            $status = collect(['GENERATE', GeneralPurchaseOrder::STATUS_PURCHASING, GeneralPurchaseOrder::STATUS_COMPLETED])
                ->first(fn (string $candidate) => $this->canViewStatus($request, $candidate));

            abort_unless($status, 403);
        }

        $payload = $status === 'GENERATE'
            ? $this->generationListPayload($request, $branch)
            : $this->listPayload($request, $branch, $status);

        return Inertia::render('Inventory/PurchaseOrders', array_merge($payload, [
            'purchaseCycle' => [
                'id' => $cycle->id,
                'folio' => $cycle->folio,
                'status' => $cycle->status,
            ],
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
            'items.product:id,image,cost,description',
            'branchOrders.branch',
            'branchOrders.items',
        ]);

        return Inertia::render('Inventory/PurchaseOrderCapture', [
            'currentBranch' => $branch,
            'orderDB' => $this->orderPayload($generalPurchaseOrder),
        ]);
    }

    private function generationListPayload(Request $request, Branch $branch): array
    {
        $perPage = TablePagination::resolvePerPage($request, 25);

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
                'search' => '',
                'status' => 'GENERATE',
                'per_page' => $perPage,
            ],
        ];
    }

    private function listPayload(Request $request, Branch $branch, string $status): array
    {

        $perPage = TablePagination::resolvePerPage($request, 25);

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'status' => $status,
            'per_page' => $perPage,
        ];

        $orders = GeneralPurchaseOrder::query()
            ->with(['branchOrders:id,general_purchase_order_id,branch_id'])
            ->withCount('items')
            ->where('status', $status);

        FlexibleSearch::apply($orders, $filters['search'], function ($query, $phrase, $terms) {
            FlexibleSearch::orWhereColumns($query, ['general_purchase_orders.folio'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'items', ['product_name', 'product_code'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'items.product.barcodes', ['code'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'branchOrders.branch', ['name'], $phrase, $terms);
        });

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
            'items.product:id,image,cost,description',
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
            'draft_id' => ['nullable', 'integer', 'exists:general_purchase_orders,id'],
            'record_version' => ['nullable', 'required_with:draft_id', 'date'],
        ]);

        $cycle = $this->cycles->currentOpenCycle($request->user());
        $order = $this->cycles->consolidate(
            $cycle,
            $request->user(),
            $payload['order_ids'],
            $payload['draft_id'] ?? null,
            $payload['record_version'] ?? null,
        );

        return redirect()->back()->with(
            'success',
            $order
                ? 'Orden general generada correctamente.'
                : 'El ciclo se cerró sin productos por comprar.'
        );
    }

    public function saveDraft(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $payload = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'integer', 'distinct'],
            'draft_id' => ['nullable', 'integer', 'exists:general_purchase_orders,id'],
            'record_version' => ['nullable', 'required_with:draft_id', 'date'],
        ]);

        $cycle = $this->cycles->currentOpenCycle($request->user());
        $this->cycles->saveGeneralDraft(
            $cycle,
            $request->user(),
            $payload['order_ids'],
            $payload['draft_id'] ?? null,
            $payload['record_version'] ?? null,
        );

        return redirect()->back()->with('success', 'Borrador de orden general guardado correctamente.');
    }

    public function sourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
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
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

        $payload = $request->validate([
            'review_status' => [
                'required',
                Rule::in([
                    PurchaseOrder::REVIEW_APPROVED,
                    PurchaseOrder::REVIEW_REJECTED,
                ]),
            ],
        ]);

        DB::transaction(function () use ($purchaseOrder, $payload, $request) {
            $lockedOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            abort_if($lockedOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');
            $lockedOrder->update([
                'review_status' => $payload['review_status'],
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
        }, 3);
        $purchaseOrder->refresh();

        $label = $payload['review_status'] === PurchaseOrder::REVIEW_APPROVED
            ? 'aprobada'
            : 'rechazada';

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

        return redirect()->back()->with('success', "Orden {$label} correctamente.");
    }

    public function updateSourceOrder(Request $request, Branch $branch, PurchaseOrder $purchaseOrder)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);
        $this->abortIfCannotManageSourceOrder($request, $purchaseOrder);
        abort_if($purchaseOrder->general_purchase_order_id, 422, 'La orden ya forma parte de una Orden de compra general.');

        $purchaseOrder->loadMissing('branch');
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

        app(SystemAuditService::class)->record('purchase-orders', 'inventory_edit', 'success', $request, [
            'record_type' => PurchaseOrder::class,
            'record_id' => $updatedOrder->id,
            'record_label' => $updatedOrder->folio,
            'observations' => 'Inventario modificó una Orden de compra pendiente antes de integrarla a una Orden de compra general.',
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
            'items.*.purchase_presentation' => ['required', 'string', 'max:30'],
            'items.*.package_quantity' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.purchase_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:999999.99'],
            'items.*.purchase_notes' => ['nullable', 'string', 'max:1000'],
            'items.*.unavailable' => ['nullable', 'boolean'],
        ]);
    }

    private function generationPayload(Request $request): array
    {
        $user = $request->user();
        $user->loadMissing('role');
        $draft = GeneralPurchaseOrder::query()
            ->with(['branchOrders.branch'])
            ->where('created_by', $user->id)
            ->where('status', GeneralPurchaseOrder::STATUS_DRAFT)
            ->latest('id')
            ->first();
        $accessibleBranchIds = $user->accessibleBranchIds();
        $isInventoryUser = $user->role?->name === 'Inventario';

        $orders = PurchaseOrder::query()
            ->with(['branch', 'user', 'assignedTo', 'reviewedBy'])
            ->withCount('items')
            ->withCount('transfers')
            ->withSum('items as requested_quantity', 'requested_quantity')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', PurchaseOrder::STATUS_GENERATED)
            ->whereNotNull('assigned_to_user_id')
            ->when($isInventoryUser, fn ($query) => $query->where('assigned_to_user_id', $user->id))
            ->where(function ($query) use ($draft) {
                $query->whereNull('general_purchase_order_id');

                if ($draft) {
                    $query->orWhere('general_purchase_order_id', $draft->id);
                }
            })
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
                'in_draft' => $draft
                    && (int) $order->general_purchase_order_id === (int) $draft->id,
            ]);
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
            ->map(fn (User $inventoryUser) => [
                'id' => $inventoryUser->id,
                'name' => $inventoryUser->name,
                'branch_ids' => $inventoryUser->accessibleBranchIds(),
            ])
            ->values();

        return [
            'branches' => $branches,
            'orders' => $orders->values(),
            'inventory_users' => $inventoryUsers,
            'draft' => $draft ? [
                'id' => $draft->id,
                'order_ids' => $draft->branchOrders->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'updated_at' => $draft->updated_at,
            ] : null,
        ];
    }

    private function sourceOrderPayload(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->id,
            'folio' => $purchaseOrder->folio,
            'status' => $purchaseOrder->status,
            'record_version' => $purchaseOrder->updated_at?->toJSON(),
            'status_label' => $this->reviewStatusLabel($purchaseOrder->review_status),
            'review_status' => $purchaseOrder->review_status,
            'review_status_label' => $this->reviewStatusLabel($purchaseOrder->review_status),
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
        abort_unless($purchaseOrder->assigned_to_user_id, 404);
        abort_unless($user->hasBranchAccess((int) $purchaseOrder->branch_id), 403);

        if ($user->role?->name === 'Inventario') {
            abort_unless((int) $purchaseOrder->assigned_to_user_id === (int) $user->id, 403);
        }
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
                    'branch_breakdown' => $breakdown,
                    'image_url' => $item->product?->image
                        ? route('inventory.products.image', ['product' => $item->product_id])
                        : null,
                    'product_description' => $item->product_description
                        ?: $item->product?->description,
                    'previous_cost' => (float) ($item->estimated_unit_price ?? $item->product?->cost ?? 0),
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
}
