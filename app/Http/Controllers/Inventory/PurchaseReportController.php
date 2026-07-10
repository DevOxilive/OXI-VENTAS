<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PurchaseReport;
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
                'code' => $item->product?->code ?? '',
                'main_barcode' => $item->product?->barcodes?->first()?->code ?? '',
                'barcodes' => $item->product?->barcodes?->pluck('code')->values() ?? [],
                'category_id' => $item->product?->category?->id,
                'category_name' => $item->product?->category?->name ?? 'Sin categoria',
                'category' => $item->product?->category?->name ?? 'Sin categoria',
                'stock' => (float) $item->stock,
                'min_stock' => (float) $item->min_stock,
                'price' => (float) $item->price,
                'label' => trim(($item->product?->name ?? 'Producto sin nombre')
                    . ' - '
                    . ($item->product?->barcodes?->first()?->code ?? 'Sin codigo')),
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
            'categoriesDB' => $this->categoryOptions($branch),
        ]);
    }

    public function show(Branch $branch, PurchaseReport $purchaseReport)
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
                        'code',
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
}
