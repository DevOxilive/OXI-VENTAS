<?php

namespace App\Http\Controllers\Inventory;

use App\Events\RealtimeActivityLogged;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\User;
use App\Services\PurchaseCycleService;
use App\Services\PendingPurchaseOrderEditor;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    use AuthorizesBranchAccess;

    public function salesPurchaseLists(Request $request)
    {
        $branches = $this->accessiblePurchaseBranches($request);
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
                'inventoryUsersDB' => [],
                'purchaseCycle' => [],
            ]);
        }

        return $this->index($request, $branch);
    }

    public function salesPurchaseOrders(Request $request)
    {
        $branches = $this->accessiblePurchaseBranches($request);
        $branch = $this->selectedPurchaseBranch($request, $branches);

        if (! $branch) {
            return Inertia::render('Inventory/BranchPurchaseOrders', [
                'selectorMode' => true,
                'currentBranch' => null,
                'branchesDB' => $this->mapPurchaseBranches($branches),
                'ordersDB' => ['data' => []],
                'filters' => ['status' => PurchaseOrder::STATUS_GENERATED],
            ]);
        }

        return $this->reportsIndex($request, $branch);
    }

    public function reportsIndex(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $perPage = TablePagination::resolvePerPage($request, 25);
        $status = $request->input('status', PurchaseOrder::STATUS_GENERATED);
        $allowedStatuses = [
            PurchaseOrder::STATUS_GENERATED,
            PurchaseOrder::STATUS_REVIEW,
            PurchaseOrder::STATUS_COMPLETED,
        ];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = PurchaseOrder::STATUS_GENERATED;
        }

        $filters = [
            'status' => $status,
            'per_page' => $perPage,
        ];

        $orders = PurchaseOrder::query()
            ->withCount('items')
            ->where('branch_id', $branch->id)
            ->where('status', $filters['status']);

        $orders = $orders
            ->latest('id')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'folio' => $order->folio,
                'status' => $order->status,
                'status_label' => $this->statusLabel($order->status),
                'inventory_edit_label' => $order->inventory_edited_at ? 'Editado' : null,
                'items_count' => $order->items_count,
                'display_date' => $order->completed_at ?? $order->generated_at ?? $order->created_at,
            ]);

        return Inertia::render('Inventory/BranchPurchaseOrders', [
            'currentBranch' => $branch,
            'selectorMode' => false,
            'branchesDB' => $this->mapPurchaseBranches($this->accessiblePurchaseBranches($request)),
            'ordersDB' => $orders,
            'filters' => $filters,
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
            'assigned_to_user_id' => [
                $request->boolean('generate_order') ? 'required' : 'nullable',
                'integer',
                'exists:users,id',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
        ]);

        $generateOrder = (bool) ($validated['generate_order'] ?? false);
        $assignedUser = $this->assignedInventoryUser(
            $branch,
            $validated['assigned_to_user_id'] ?? null,
            $generateOrder,
        );

        $report = DB::transaction(function () use ($assignedUser, $branch, $generateOrder, $request, $validated) {
            $report = PurchaseOrder::create([
                'branch_id' => $branch->id,
                'user_id' => $request->user()?->id,
                'assigned_to_user_id' => $assignedUser?->id,
                'source' => PurchaseOrder::SOURCE_CENTRAL,
                'status' => $generateOrder
                    ? PurchaseOrder::STATUS_GENERATED
                    : PurchaseOrder::STATUS_DRAFT,
                'generated_at' => $generateOrder ? now() : null,
            ]);

            foreach ($validated['items'] as $item) {
                $product = BranchProduct::query()
                    ->with('product')
                    ->where('branch_id', $branch->id)
                    ->findOrFail($item['branch_product_id']);

                $estimatedPrice = (float) ($product->product?->cost ?? 0);
                $requestedQuantity = (float) $item['requested_quantity'];

                $report->items()->create([
                    'branch_product_id' => $product->id,
                    'product_id' => $product->product_id,
                    'current_stock' => $product->stock,
                    'min_stock' => $product->min_stock,
                    'requested_quantity' => $requestedQuantity,
                    'estimated_price' => $estimatedPrice,
                    'estimated_total' => $estimatedPrice * $requestedQuantity,
                    'status' => PurchaseOrderItem::STATUS_REQUESTED,
                ]);
            }

            $report->update(['folio' => $this->makeFolio($report)]);
            $this->refreshTotals($report);

            if ($generateOrder) {
                app(PurchaseCycleService::class)->registerOrder($report, $request->user());
            }

            return $report;
        });

        if ($generateOrder && $assignedUser) {
            $this->notifyInventoryAssignment($report, $assignedUser, $branch);
        }

        return redirect()->route('ventas.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with(
            'success',
            $generateOrder
                ? 'Orden generada correctamente.'
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

        if ($purchaseReport->status === PurchaseOrder::STATUS_GENERATED) {
            $editor = app(PendingPurchaseOrderEditor::class);
            $updatedOrder = $editor->update(
                $purchaseReport,
                $branch,
                $request->validate($editor->rules())['items'],
                $request->user(),
                false,
            );

            return redirect()->back()->with('success', "La orden {$updatedOrder->folio} fue actualizada correctamente.");
        }

        abort_unless($purchaseReport->status === PurchaseOrder::STATUS_DRAFT, 422, 'Solo se pueden modificar listas en borrador.');
        abort_if($purchaseReport->general_purchase_order_id, 422, 'La solicitud ya forma parte de una orden general.');
        $this->abortIfDraftDoesNotBelongToUser($request, $purchaseReport);

        $validated = $this->validateOrderPayload($request);
        $assignedUser = $this->assignedInventoryUser(
            $branch,
            $validated['assigned_to_user_id'] ?? null,
            false,
        );

        $purchaseReport->update([
            'assigned_to_user_id' => $assignedUser?->id,
        ]);

        $this->syncItems($purchaseReport, $branch, $validated['items']);
        $this->refreshTotals($purchaseReport);

        return redirect()->back()->with('success', 'Orden actualizada correctamente.');
    }

    public function generate(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        abort_unless($purchaseReport->status === PurchaseOrder::STATUS_DRAFT, 422);
        $this->abortIfDraftDoesNotBelongToUser($request, $purchaseReport);

        $validated = $this->validateOrderPayload($request, true);
        $assignedUser = $this->assignedInventoryUser(
            $branch,
            $validated['assigned_to_user_id'] ?? null,
            true,
        );

        DB::transaction(function () use ($assignedUser, $branch, $purchaseReport, $request, $validated) {
            $purchaseReport->update([
                'assigned_to_user_id' => $assignedUser->id,
            ]);

            $this->syncItems($purchaseReport, $branch, $validated['items']);
            $this->refreshTotals($purchaseReport);

            $purchaseReport->update([
                'status' => PurchaseOrder::STATUS_GENERATED,
                'generated_at' => now(),
            ]);

            app(PurchaseCycleService::class)->registerOrder($purchaseReport, $request->user());
        });

        $this->notifyInventoryAssignment($purchaseReport, $assignedUser, $branch);

        return redirect()->route('ventas.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with(
            'success',
            'Orden generada correctamente.'
        );
    }

    public function complete(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        abort_unless(
            $purchaseReport->status === PurchaseOrder::STATUS_REVIEW,
            422,
            'La orden debe estar por revisar antes de confirmar la recepcion.'
        );

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.received_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.receipt_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $purchaseReport->load('items');
        $receivedItems = collect($validated['items'])->keyBy(fn ($item) => (int) $item['id']);

        if ($receivedItems->keys()->sort()->values()->all() !== $purchaseReport->items->pluck('id')->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'items' => 'Debes confirmar la recepcion de todos los productos de la orden.',
            ]);
        }

        DB::transaction(function () use ($purchaseReport, $receivedItems, $request, $validated) {
            foreach ($purchaseReport->items as $item) {
                $received = $receivedItems->get((int) $item->id);
                $receivedQuantity = (float) $received['received_quantity'];
                $requestedQuantity = (float) $item->requested_quantity;

                $item->update([
                    'received_quantity' => $receivedQuantity,
                    'receipt_notes' => $received['receipt_notes'] ?? null,
                    'status' => $receivedQuantity <= 0
                        ? PurchaseOrderItem::STATUS_UNAVAILABLE
                        : (abs($receivedQuantity - $requestedQuantity) < 0.001
                            ? PurchaseOrderItem::STATUS_PURCHASED
                            : PurchaseOrderItem::STATUS_ADJUSTED),
                ]);
            }

            $purchaseReport->update([
                'status' => PurchaseOrder::STATUS_COMPLETED,
                'completed_by' => $request->user()?->id,
                'completed_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Orden completada correctamente.');
    }

    public function destroy(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        abort_unless($purchaseReport->status === PurchaseOrder::STATUS_DRAFT, 422);
        $this->abortIfDraftDoesNotBelongToUser($request, $purchaseReport);

        $purchaseReport->delete();

        return redirect()->back()->with('success', 'Borrador eliminado correctamente.');
    }

    public function submitEmpty(
        Request $request,
        Branch $branch,
        PurchaseCycleService $cycles
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $cycles->registerEmptyBranch($branch, $request->user());

        return redirect()->back()->with(
            'success',
            'La sucursal confirmó que no necesita productos en este ciclo.'
        );
    }

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $perPage = TablePagination::resolvePerPage($request, 50);

        $filters = [
            'search' => $request->input('search', ''),
            'category_id' => $request->input('category_id', ''),
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
                'category_name' => $item->product?->category?->name ?? 'Sin categoria',
                'category' => $item->product?->category?->name ?? 'Sin categoria',
                'stock' => (float) $item->stock,
                'min_stock' => (float) $item->min_stock,
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
            ->where('user_id', $request->user()?->id)
            ->where('status', PurchaseOrder::STATUS_DRAFT)
            ->latest()
            ->paginate(12, ['*'], 'lists_page')
            ->withQueryString()
            ->through(fn (PurchaseOrder $order) => [
                'id' => $order->id,
                'branch_id' => $order->branch_id,
                'folio' => $order->folio,
                'status' => $order->status,
                'assigned_to_user_id' => $order->assigned_to_user_id,
                'items_count' => $order->items_count,
                'created_at' => $order->created_at,
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
                        'requested_quantity' => $item->requested_quantity,
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
                                'barcodes' => $product?->barcodes?->map(fn ($barcode) => [
                                    'id' => $barcode->id,
                                    'code' => $barcode->code,
                                ])->values() ?? [],
                            ],
                        ],
                    ];
                })->values(),
            ]);

        $cycle = app(PurchaseCycleService::class)->currentOpenCycle($request->user());
        $cycle->load(['branches' => fn ($query) => $query->where('branch_id', $branch->id)]);
        $participation = $cycle->branches->first();

        return Inertia::render('Inventory/PurchaseReport', [
            'selectorMode' => false,
            'currentBranch' => $branch,
            'branchesDB' => $this->mapPurchaseBranches($this->accessiblePurchaseBranches($request)),
            'productsDB' => $products,
            'reportsDB' => $reports,
            'filters' => $filters,
            'categoriesDB' => $this->categoryOptions($branch),
            'inventoryUsersDB' => $this->inventoryUsersForBranch($branch),
            'purchaseCycle' => [
                'id' => $cycle->id,
                'folio' => $cycle->folio,
                'submitted' => (bool) $participation?->submitted_at,
                'without_items' => (bool) $participation?->submitted_without_items,
                'order_id' => $participation?->purchase_order_id,
            ],
        ]);
    }

    public function show(Branch $branch, PurchaseOrder $purchaseReport)
    {
        $this->abortIfUserCannotAccessBranch(request(), $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);

        $purchaseReport->load([
            'user',
            'items.branchProduct.product.barcodes',
            'items.branchProduct.product.category',
        ]);

        return Inertia::render('Inventory/PurchaseReportShow', [
            'currentBranch' => $branch,
            'reportDB' => $purchaseReport,
        ]);
    }

    public function reportOrder(Request $request, Branch $branch, PurchaseOrder $purchaseReport)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);

        $purchaseReport->load([
            'branch:id,name',
            'user:id,name',
            'items.branchProduct.product.barcodes',
            'items.branchProduct.product.category',
        ]);

        return response()->json([
            'id' => $purchaseReport->id,
            'folio' => $purchaseReport->folio,
            'status' => $purchaseReport->status,
            'status_label' => $this->statusLabel($purchaseReport->status),
            'requested_at' => $purchaseReport->generated_at ?? $purchaseReport->created_at,
            'items_count' => $purchaseReport->items->count(),
            'branch' => $purchaseReport->branch ? ['id' => $purchaseReport->branch->id, 'name' => $purchaseReport->branch->name] : null,
            'user' => $purchaseReport->user ? ['id' => $purchaseReport->user->id, 'name' => $purchaseReport->user->name] : null,
            'items' => $purchaseReport->items->map(function (PurchaseOrderItem $item) {
                $branchProduct = $item->branchProduct;
                $product = $branchProduct?->product;

                return [
                    'id' => $item->id,
                    'branch_product_id' => $item->branch_product_id,
                    'product_name' => $product?->name ?? 'Producto sin nombre',
                    'product_code' => $product?->barcodes?->first()?->code ?: ($branchProduct?->barcode ?? ''),
                    'category_name' => $product?->category?->name ?? 'Sin categoria',
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
                'product.category',
                'product.barcodes',
            ])
            ->where('branch_id', $branch->id)
            ->when($filters['search'], function ($query, $search) {
                FlexibleSearch::apply($query, $search, function ($searchQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($searchQuery, [
                        'branch_products.barcode',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product', [
                        'name',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                        'code',
                    ], $phrase, $terms);
                });
            })
            ->when($filters['category_id'], function ($query, $categoryId) {
                $query->whereHas('product', function ($productQuery) use ($categoryId) {
                    $productQuery->where('category_id', $categoryId);
                });
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
            ->select(['categories.id', 'categories.name'])
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('branch_products', 'branch_products.product_id', '=', 'products.id')
            ->where('branch_products.branch_id', $branch->id)
            ->distinct()
            ->orderBy('categories.name')
            ->get();
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
            $branch = $branches->first(fn (Branch $branch) =>
                (string) $branch->id === $requestedBranch
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

    private function inventoryUsersForBranch(Branch $branch): array
    {
        return User::query()
            ->with(['role.permissions', 'permissions', 'branches'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Inventario'))
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasBranchAccess((int) $branch->id))
            ->map(fn (User $user) => [
                'id' => $user->id,
                'value' => $user->id,
                'label' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function assignedInventoryUser(
        Branch $branch,
        int|string|null $userId,
        bool $required
    ): ?User {
        if (! $userId) {
            if ($required) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'Selecciona a la persona de Inventario responsable de la orden.',
                ]);
            }

            return null;
        }

        $user = User::query()
            ->with(['role.permissions', 'permissions', 'branches'])
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'Inventario'))
            ->find($userId);

        if (! $user || ! $user->hasBranchAccess((int) $branch->id)) {
            throw ValidationException::withMessages([
                'assigned_to_user_id' => 'La persona seleccionada no pertenece a Inventario o no tiene acceso a esta sucursal.',
            ]);
        }

        return $user;
    }

    private function notifyInventoryAssignment(
        PurchaseOrder $purchaseOrder,
        User $assignedUser,
        Branch $branch
    ): void {
        event(new RealtimeActivityLogged(
            "La orden {$purchaseOrder->folio} de {$branch->name} se asigno a {$assignedUser->name}.",
            'Ventas',
            'assigned',
            $purchaseOrder->folio,
            [$assignedUser->id],
        ));
    }

    private function abortIfDraftDoesNotBelongToUser(
        Request $request,
        PurchaseOrder $purchaseOrder
    ): void {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            return;
        }

        abort_unless(
            (int) $purchaseOrder->user_id === (int) $request->user()?->id,
            403,
            'Sólo puedes modificar tus propios borradores.'
        );
    }

    private function validateOrderPayload(
        Request $request,
        bool $requireAssignedInventoryUser = false
    ): array
    {
        $rules = [
            'assigned_to_user_id' => [
                $requireAssignedInventoryUser ? 'required' : 'nullable',
                'integer',
                'exists:users,id',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
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

            $requestedQuantity = (float) $item['requested_quantity'];
            $estimatedPrice = (float) ($branchProduct->product?->cost ?? 0);
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

    private function makeFolio(PurchaseOrder $purchaseOrder): string
    {
        return sprintf('OC-%s-%04d', now()->format('Ymd'), $purchaseOrder->id);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            PurchaseOrder::STATUS_DRAFT => 'Borrador',
            PurchaseOrder::STATUS_GENERATED => 'Pendiente',
            PurchaseOrder::STATUS_REVIEW => 'Por revisar',
            PurchaseOrder::STATUS_COMPLETED => 'Completada',
            PurchaseOrder::STATUS_CANCELLED => 'Cancelada',
            default => $status,
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
