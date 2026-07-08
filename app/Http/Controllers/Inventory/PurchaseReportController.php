<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\PurchaseReport;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseReportController extends Controller
{
    public function create(Request $request, Branch $branch)
    {
        $perPage = TablePagination::resolvePerPage($request, 50);

        $filters = [
            'search' => $request->input('search', ''),
            'category' => $request->input('category', ''),
            'stock' => $request->input('stock', ''),
            'per_page' => $perPage,
        ];

        $products = BranchProduct::query()
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
                        'code',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                        'code',
                    ], $phrase, $terms);
                });
            })
            ->when($filters['category'], function ($query, $category) {
                $query->whereHas('product.category', fn($q) => $q->where('name', $category));
            })
            ->when($filters['stock'] === 'LOW', function ($query) {
                $query->whereColumn('stock', '<=', 'min_stock')
                    ->where('stock', '>', 0);
            })
            ->when($filters['stock'] === 'OUT', function ($query) {
                $query->where('stock', '<=', 0);
            })
            ->orderBy('stock')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product?->id,
                'name' => $item->product?->name ?? 'Producto sin nombre',
                'code' => $item->product?->code ?? '',
                'main_barcode' => $item->product?->barcodes?->first()?->code ?? '',
                'barcodes' => $item->product?->barcodes?->pluck('code')->values() ?? [],
                'category' => $item->product?->category?->name ?? 'Sin categoria',
                'stock' => (float) $item->stock,
                'min_stock' => (float) $item->min_stock,
                'price' => (float) $item->price,
            ]);

        $filterOptions = BranchProduct::query()
            ->with(['product.category'])
            ->where('branch_id', $branch->id)
            ->get()
            ->map(fn($item) => [
                'category' => $item->product?->category?->name,
            ]);

        return redirect()->route('inventario.branches.purchase-reports.index', [
            'branch' => $branch->id,
        ]);

        return Inertia::render('Inventory/PurchaseReport', [
            'currentBranch' => $branch,
            'productsDB' => $products,
            'filters' => $filters,
            'categoriesDB' => $filterOptions->pluck('category')->filter()->unique()->values(),
        ]);
    }

    public function store(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.branch_product_id' => ['required', 'exists:branch_products,id'],
            'items.*.requested_quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $report = PurchaseReport::create([
            'branch_id' => $branch->id,
            'user_id' => $request->user()?->id,
            'status' => 'DRAFT',
            'notes' => $validated['notes'] ?? null,
        ]);

        foreach ($validated['items'] as $item) {
            $product = BranchProduct::findOrFail(
                $item['branch_product_id']
            );

            $report->items()->create([
                'branch_product_id' => $product->id,
                'current_stock' => $product->stock,
                'min_stock' => $product->min_stock,
                'requested_quantity' => $item['requested_quantity'],
                'estimated_price' => $product->price,
                'estimated_total' => $product->price * $item['requested_quantity'],
                'notes' => $item['notes'] ?? null,
            ]);
        }

        return redirect()->route('inventario.branches.purchase-reports.index', [
            'branch' => $branch->id,
        ])->with('success', 'Borrador guardado correctamente.');
    }

    public function update(
        Request $request,
        Branch $branch,
        PurchaseReport $purchaseReport
    ) {
        //
    }

    public function generate(
        Branch $branch,
        PurchaseReport $purchaseReport
    ) {
        $purchaseReport->update([
            'status' => 'GENERATED',
            'generated_at' => now(),
        ]);

        return redirect()->back()->with(
            'success',
            'Reporte generado correctamente.'
        );
    }

    public function index(Request $request, Branch $branch)
    {
        $perPage = TablePagination::resolvePerPage($request, 50);

        $filters = [
            'search' => $request->input('search', ''),
            'category' => $request->input('category', ''),
            'stock' => $request->input('stock', ''),
            'per_page' => $perPage,
        ];

        $products = BranchProduct::query()
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
                        'code',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($searchQuery, 'product.barcodes', [
                        'code',
                    ], $phrase, $terms);
                });
            })
            ->when($filters['category'], fn($query, $category) => $query->whereHas('product.category', fn($q) => $q->where('name', $category)))
            ->when($filters['stock'] === 'LOW', fn($query) => $query->whereColumn('stock', '<=', 'min_stock')->where('stock', '>', 0))
            ->when($filters['stock'] === 'OUT', fn($query) => $query->where('stock', '<=', 0))
            ->orderBy('stock')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn($item) => [
                'id' => $item->id,
                'product_id' => $item->product?->id,
                'name' => $item->product?->name ?? 'Producto sin nombre',
                'code' => $item->product?->code ?? '',
                'main_barcode' => $item->product?->barcodes?->first()?->code ?? '',
                'barcodes' => $item->product?->barcodes?->pluck('code')->values() ?? [],
                'category' => $item->product?->category?->name ?? 'Sin categoria',
                'stock' => (float) $item->stock,
                'min_stock' => (float) $item->min_stock,
                'price' => (float) $item->price,
            ]);

        $filterOptions = BranchProduct::query()
            ->with(['product.category'])
            ->where('branch_id', $branch->id)
            ->get()
            ->map(fn($item) => [
                'category' => $item->product?->category?->name,
            ]);

        $reports = PurchaseReport::query()
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
            'categoriesDB' => $filterOptions->pluck('category')->filter()->unique()->values(),
        ]);
    }

    public function show(Branch $branch, PurchaseReport $purchaseReport)
    {
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
}
