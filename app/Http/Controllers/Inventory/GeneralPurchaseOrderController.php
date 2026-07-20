<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\GeneralPurchaseOrder;
use App\Models\GeneralPurchaseOrderItem;
use App\Models\PurchaseCycle;
use App\Services\PurchaseCycleService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeneralPurchaseOrderController extends Controller
{
    use AuthorizesBranchAccess;

    public function __construct(private readonly PurchaseCycleService $cycles) {}

    public function index(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $cycle = $this->cycles->currentOpenCycle($request->user());
        $cycle->load(['branches.branch', 'branches.order']);
        $status = $request->input('status', GeneralPurchaseOrder::STATUS_PURCHASING);

        if (! in_array($status, [GeneralPurchaseOrder::STATUS_PURCHASING, GeneralPurchaseOrder::STATUS_COMPLETED], true)) {
            $status = GeneralPurchaseOrder::STATUS_PURCHASING;
        }

        return Inertia::render('Inventory/PurchaseOrders', array_merge(
            $this->listPayload($request, $branch, $status),
            ['purchaseCycle' => $this->cyclePayload($cycle)]
        ));
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
        abort_unless($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_PURCHASING, 404);

        $generalPurchaseOrder->load([
            'items',
            'branchOrders.branch',
            'branchOrders.items',
        ]);

        return Inertia::render('Inventory/PurchaseOrderCapture', [
            'currentBranch' => $branch,
            'orderDB' => $this->orderPayload($generalPurchaseOrder),
        ]);
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
            ->withCount(['items', 'branchOrders'])
            ->where('status', $status);

        FlexibleSearch::apply($orders, $filters['search'], function ($query, $phrase, $terms) {
            FlexibleSearch::orWhereColumns($query, ['general_purchase_orders.folio'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'items', ['product_name', 'product_code'], $phrase, $terms);
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
                'branches_count' => $order->branch_orders_count,
                'estimated_total' => $order->estimated_total,
                'actual_total' => $order->actual_total,
                'display_date' => $order->completed_at ?? $order->created_at,
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
        $canOpen = $completed
            ? $request->user()->hasPermission('inventory.purchase-orders.history')
            : ($request->user()->hasPermission('inventory.purchase-orders.view')
                || $request->user()->hasPermission('inventory.purchase-orders.update'));

        abort_unless($canOpen, 403);

        $generalPurchaseOrder->load([
            'items',
            'branchOrders.branch',
            'branchOrders.items',
        ]);

        return response()->json($this->orderPayload($generalPurchaseOrder, $completed));
    }

    public function consolidate(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $payload = $request->validate([
            'branch_ids' => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer'],
        ]);

        $cycle = $this->cycles->currentOpenCycle($request->user());
        $order = $this->cycles->consolidate($cycle, $request->user(), $payload['branch_ids']);

        return redirect()->back()->with(
            'success',
            $order
                ? 'Orden general generada correctamente.'
                : 'El ciclo se cerró sin productos por comprar.'
        );
    }

    public function update(
        Request $request,
        Branch $branch,
        GeneralPurchaseOrder $generalPurchaseOrder
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);
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
        abort_unless($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_PURCHASING, 404);

        $this->cycles->complete(
            $generalPurchaseOrder,
            $this->validatedPayload($request),
            $request->user()
        );

        return redirect()->back()->with(
            'success',
            'Compra general completada y solicitudes de sucursal cerradas correctamente.'
        );
    }

    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'purchased_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:general_purchase_order_items,id'],
            'items.*.purchase_presentation' => ['required', 'string', 'max:30'],
            'items.*.package_quantity' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.units_per_package' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.package_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.actual_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.promotion_notes' => ['nullable', 'string', 'max:255'],
            'items.*.unavailable' => ['nullable', 'boolean'],
        ]);
    }

    private function cyclePayload(PurchaseCycle $cycle): array
    {
        $branches = $cycle->branches->map(fn ($participation) => [
            'id' => $participation->branch_id,
            'name' => $participation->branch?->name ?? 'Sucursal',
            'submitted' => (bool) $participation->submitted_at,
            'without_items' => (bool) $participation->submitted_without_items,
            'has_order' => (bool) $participation->purchase_order_id,
            'order_folio' => $participation->order?->folio,
            'submitted_at' => $participation->submitted_at,
        ])->values();

        return [
            'id' => $cycle->id,
            'folio' => $cycle->folio,
            'status' => $cycle->status,
            'submitted_count' => $branches->where('submitted', true)->count(),
            'total_branches' => $branches->count(),
            'ready' => $branches->where('submitted', true)->isNotEmpty(),
            'branches' => $branches,
        ];
    }

    private function orderPayload(GeneralPurchaseOrder $order, bool $includePurchaseData = true): array
    {
        $payload = [
            'id' => $order->id,
            'folio' => $order->folio,
            'status' => $order->status,
            'completed_at' => $order->completed_at,
            'branches' => $order->branchOrders->map(fn ($branchOrder) => [
                'id' => $branchOrder->branch_id,
                'name' => $branchOrder->branch?->name ?? 'Sucursal',
                'folio' => $branchOrder->folio,
                'requested_quantity' => (float) $branchOrder->items->sum('requested_quantity'),
            ])->values(),
            'items' => $order->items->map(function (GeneralPurchaseOrderItem $item) use ($order, $includePurchaseData) {
                $breakdown = $order->branchOrders->map(function ($branchOrder) use ($item) {
                    $branchItem = $branchOrder->items->firstWhere('product_id', $item->product_id);

                    if (! $branchItem) {
                        return null;
                    }

                    return [
                        'branch_id' => $branchOrder->branch_id,
                        'branch_name' => $branchOrder->branch?->name ?? 'Sucursal',
                        'order_folio' => $branchOrder->folio,
                        'requested_quantity' => (float) $branchItem->requested_quantity,
                    ];
                })->filter()->values();

                $itemPayload = $includePurchaseData
                    ? $item->toArray()
                    : $item->only([
                        'id',
                        'product_id',
                        'product_name',
                        'product_code',
                        'base_unit',
                        'requested_quantity',
                    ]);

                return array_merge($itemPayload, ['branch_breakdown' => $breakdown]);
            })->values(),
        ];

        if (! $includePurchaseData) {
            return $payload;
        }

        return array_merge($payload, [
            'estimated_total' => $order->estimated_total,
            'gross_total' => $order->gross_total,
            'discount_total' => $order->discount_total,
            'actual_total' => $order->actual_total,
            'purchased_at' => optional($order->purchased_at)->format('Y-m-d'),
            'notes' => $order->notes,
        ]);
    }
}
