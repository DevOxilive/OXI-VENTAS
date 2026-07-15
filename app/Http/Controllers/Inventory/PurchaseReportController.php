<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    use AuthorizesBranchAccess;

    public function create(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $this->index($request, $branch);
    }

    public function store(Request $request, Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
        ]);

        $report = PurchaseOrder::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()?->id,
            'source' => PurchaseOrder::SOURCE_CENTRAL,
            'status' => PurchaseOrder::STATUS_DRAFT,
            'notes' => $validated['notes'] ?? null,
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

        $report->update([
            'folio' => $this->makeFolio($report),
        ]);

        $this->refreshTotals($report);

        return redirect()->route('inventory.branches.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with('success', 'Borrador guardado correctamente.');
    }

    public function update(
        Request $request,
        Branch $branch,
        PurchaseOrder $purchaseReport
    ) {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        abort_unless($purchaseReport->branch_id === $branch->id, 404);
        abort_if($purchaseReport->status === PurchaseOrder::STATUS_COMPLETED, 422, 'La orden completada ya no se puede modificar.');

        $validated = $this->validateOrderPayload($request, $purchaseReport);

        $purchaseReport->update([
            'notes' => $validated['notes'] ?? null,
            'purchased_at' => $validated['purchased_at'] ?? $purchaseReport->purchased_at,
            'adjustment_notes' => $validated['adjustment_notes'] ?? $purchaseReport->adjustment_notes,
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

        $purchaseReport->update([
            'status' => PurchaseOrder::STATUS_GENERATED,
            'generated_at' => now(),
        ]);

        return redirect()->back()->with(
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
        abort_unless($purchaseReport->status === PurchaseOrder::STATUS_GENERATED, 422);

        $validated = $this->validateOrderPayload($request, $purchaseReport);

        $purchaseReport->update([
            'notes' => $validated['notes'] ?? null,
            'purchased_at' => $validated['purchased_at'] ?? now()->toDateString(),
            'adjustment_notes' => $validated['adjustment_notes'] ?? null,
        ]);

        $this->syncItems($purchaseReport, $branch, $validated['items']);

        $purchaseReport->items()->each(function (PurchaseOrderItem $item) {
            if ($item->status === PurchaseOrderItem::STATUS_UNAVAILABLE) {
                $item->update([
                    'purchased_quantity' => 0,
                    'actual_price' => $item->actual_price ?? 0,
                    'discount_amount' => 0,
                    'actual_total' => 0,
                ]);

                return;
            }

            $purchasedQuantity = (float) ($item->purchased_quantity ?? $item->requested_quantity);
            $actualPrice = (float) ($item->actual_price ?? $item->estimated_price ?? 0);
            $discountAmount = min(
                (float) ($item->discount_amount ?? 0),
                $purchasedQuantity * $actualPrice
            );

            $item->update([
                'purchased_quantity' => $purchasedQuantity,
                'actual_price' => $actualPrice,
                'discount_amount' => $discountAmount,
                'actual_total' => ($purchasedQuantity * $actualPrice) - $discountAmount,
                'status' => $this->resolveItemStatus($item, $purchasedQuantity, $actualPrice, false, $discountAmount),
            ]);
        });

        $this->refreshTotals($purchaseReport);

        $purchaseReport->update([
            'status' => PurchaseOrder::STATUS_COMPLETED,
            'completed_by' => $request->user()?->id,
            'completed_at' => now(),
        ]);

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

        $purchaseReport->delete();

        return redirect()->back()->with('success', 'Borrador eliminado correctamente.');
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
                'price' => (float) ($item->product?->cost ?? 0),
                'label' => trim(($item->product?->name ?? 'Producto sin nombre')
                    . ' - '
                    . ($item->product?->barcodes?->first()?->code ?: ($item->barcode ?: 'Sin codigo'))),
            ]);

        $reports = PurchaseOrder::query()
            ->with([
                'items.branchProduct.product.barcodes',
                'items.branchProduct.product.category',
            ])
            ->withCount('items')
            ->where('branch_id', $branch->id)
            ->latest()
            ->get();

        return Inertia::render('Inventory/PurchaseReport', [
            'currentBranch' => $branch,
            'productsDB' => $products,
            'reportsDB' => $reports,
            'filters' => $filters,
            'categoriesDB' => $this->categoryOptions($branch),
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

    private function validateOrderPayload(Request $request, PurchaseOrder $purchaseOrder): array
    {
        $rules = [
            'notes' => ['nullable', 'string'],
            'purchased_at' => ['nullable', 'date'],
            'adjustment_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999.99'],
            'items.*.purchased_quantity' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.actual_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'items.*.unavailable' => ['nullable', 'boolean'],
        ];

        if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT) {
            unset($rules['purchased_at'], $rules['adjustment_notes']);
        }

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
}
