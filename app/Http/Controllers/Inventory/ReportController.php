<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Exports\InventoryReportExport;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryReportService;
use App\Support\TablePagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    use AuthorizesBranchAccess;

    private const REPORT_TYPES = [
        'audits' => [
            'permission' => 'reports.audits.view',
            'route' => 'inventory.branches.reports.audits',
            'label' => 'Reportes de auditoría',
        ],
        'cash-closures' => [
            'permission' => 'reports.cash-closures.view',
            'route' => 'inventory.branches.reports.cash-closures',
            'label' => 'Reportes de cortes',
        ],
        'inventory' => [
            'permission' => 'reports.inventory.view',
            'route' => 'inventory.branches.reports.inventory',
            'label' => 'Reportes de inventario',
        ],
        'movements' => [
            'permission' => 'reports.movements.view',
            'route' => 'inventory.branches.reports.movements',
            'label' => 'Reportes de movimientos',
        ],
    ];

    public function landing(Request $request)
    {
        $report = collect(self::REPORT_TYPES)
            ->keys()
            ->first(fn (string $report) => $request->user()?->hasPermission(self::REPORT_TYPES[$report]['permission']));

        abort_unless($report, 403, 'No tienes permiso para consultar reportes.');

        return redirect()->route('inventory.reports.select', [
            'report' => $report,
        ]);
    }

    public function select(Request $request, string $report)
    {
        $reportConfig = self::REPORT_TYPES[$report] ?? null;

        abort_unless($reportConfig, 404);
        abort_unless(
            $request->user()?->hasPermission($reportConfig['permission']),
            403,
            'No tienes permiso para consultar este reporte.'
        );

        $branches = $this->reportBranches($request);

        if ($branches->count() === 1) {
            return redirect()->route($reportConfig['route'], [
                'branch' => $branches->first()->id,
            ]);
        }

        return Inertia::render('Inventory/Reports/Branches', [
            'branches' => $branches,
            'selectedReport' => [
                'key' => $report,
                'label' => $reportConfig['label'],
                'routeName' => $reportConfig['route'],
            ],
        ]);
    }

    public function index(Branch $branch)
    {
        $this->abortIfUserCannotAccessBranch(request(), $branch);

        $report = collect(self::REPORT_TYPES)
            ->keys()
            ->first(fn (string $report) => request()->user()?->hasPermission(self::REPORT_TYPES[$report]['permission']));

        abort_unless($report, 403, 'No tienes permiso para consultar reportes.');

        return redirect()->route(self::REPORT_TYPES[$report]['route'], [
            'branch' => $branch->id,
        ]);
    }

    public function inventory(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = $this->resolveFilters($request);
        unset($filters['product_id']);
        $activeReport = $request->input('report', 'dashboard');
        $activeReport = $activeReport === 'movements' ? 'dashboard' : $activeReport;
        $filters['report'] = $activeReport;

        $reports = [
            'inventory' => [],
            'movements' => [],
            'expirations' => [],
            'rotation' => [],
            'attentionProducts' => [],
        ];

        $reports[$this->reportDataKey($activeReport)] = $reportService->rows($branch, $filters, true);

        return Inertia::render('Inventory/Reports/InventoryReports', [
            'currentBranch' => $branch,
            'activeReport' => $activeReport,
            'filters' => $filters,
            'catalogs' => [
                'categories' => $this->categoryOptions($branch),
            ],
            'summary' => $reportService->summary($branch),
            'reports' => $reports,
            'reportHistory' => [],
        ]);
    }

    public function movements(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = array_merge($this->resolveFilters($request), [
            'report' => 'movements',
        ]);
        unset($filters['product_id']);

        return Inertia::render('Inventory/Reports/InventoryMovements', [
            'currentBranch' => $branch,
            'filters' => $filters,
            'catalogs' => [
                'categories' => $this->categoryOptions($branch),
                'users' => $this->movementUserOptions($branch),
                'movementTypes' => $this->movementTypes(),
                'movementReasons' => $this->movementReasons(),
            ],
            'movements' => $reportService->rows($branch, $filters, true),
        ]);
    }

    public function exportExcel(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = $this->resolveFilters($request);
        unset($filters['product_id']);
        $rows = $reportService->rows($branch, $filters);
        $title = $this->reportTitle($filters['report'] ?? 'dashboard');
        $fileName = 'reporte-inventario-' . now()->format('Y-m-d-H-i') . '.xlsx';

        return Excel::download(
            new InventoryReportExport($rows, $title, 'inventory'),
            $fileName
        );
    }

    public function exportPdf(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = $this->resolveFilters($request);
        unset($filters['product_id']);
        $rows = $reportService->rows($branch, $filters);

        $pdf = Pdf::loadView('pdf.inventory-report', [
            'branch' => $branch,
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $reportService->summary($branch),
            'title' => $this->reportTitle($filters['report'] ?? 'dashboard'),
            'reportType' => 'inventory',
        ])->setPaper('letter', 'landscape');

        return $pdf->download('reporte-inventario-' . now()->format('Y-m-d-H-i') . '.pdf');
    }

    public function exportMovementsExcel(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = array_merge($this->resolveFilters($request), [
            'report' => 'movements',
        ]);
        unset($filters['product_id']);
        $rows = $reportService->rows($branch, $filters);
        $fileName = 'reporte-movimientos-inventario-' . now()->format('Y-m-d-H-i') . '.xlsx';

        return Excel::download(
            new InventoryReportExport($rows, 'Movimientos', 'movements'),
            $fileName
        );
    }

    public function exportMovementsPdf(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $filters = array_merge($this->resolveFilters($request), [
            'report' => 'movements',
        ]);
        unset($filters['product_id']);
        $rows = $reportService->rows($branch, $filters);

        $pdf = Pdf::loadView('pdf.inventory-report', [
            'branch' => $branch,
            'filters' => $filters,
            'rows' => $rows,
            'summary' => $reportService->summary($branch),
            'title' => 'Movimientos',
            'reportType' => 'movements',
        ])->setPaper('letter', 'landscape');

        return $pdf->download('reporte-movimientos-inventario-' . now()->format('Y-m-d-H-i') . '.pdf');
    }

    private function resolveFilters(Request $request): array
    {
        return [
            'period' => $request->input('period'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'product_id' => $request->input('product_id'),
            'category_id' => $request->input('category_id'),
            'status' => $request->input('status'),
            'user_id' => $request->input('user_id'),
            'movement_type' => $request->input('movement_type'),
            'movement_reason' => $request->input('movement_reason'),
            'search' => $request->input('search'),
            'per_page' => TablePagination::resolvePerPage($request, 25),
        ];
    }

    private function reportDataKey(string $activeReport): string
    {
        return match ($activeReport) {
            'movements' => 'movements',
            'expirations' => 'expirations',
            'rotation' => 'rotation',
            'dashboard' => 'inventory',
            default => 'attentionProducts',
        };
    }

    private function reportTitle(string $activeReport): string
    {
        return match ($activeReport) {
            'movements' => 'Movimientos',
            'expirations' => 'Caducidades',
            'rotation' => 'Rotacion',
            'attention' => 'Stock critico',
            default => 'Estado del inventario',
        };
    }

    private function branchProductOptions(Branch $branch)
    {
        return BranchProduct::query()
            ->with(['product:id,name', 'product.barcodes:id,product_id,code'])
            ->where('branch_id', $branch->id)
            ->orderBy(
                Product::query()
                    ->select('name')
                    ->whereColumn('products.id', 'branch_products.product_id')
                    ->limit(1)
            )
            ->get()
            ->map(fn ($branchProduct) => [
                'id' => $branchProduct->id,
                'name' => $branchProduct->product?->name ?? 'Producto sin nombre',
                'barcode' => $branchProduct->barcode
                    ?? $branchProduct->product?->barcodes?->first()?->code
                    ?? null,
                'label' => trim(($branchProduct->product?->name ?? 'Producto sin nombre')
                    . ' - '
                    . ($branchProduct->barcode ?? $branchProduct->product?->barcodes?->first()?->code ?? 'Sin codigo')),
            ])
            ->values();
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

    private function movementUserOptions(Branch $branch)
    {
        return User::query()
            ->select('users.id', 'users.name')
            ->join('stock_movements', 'stock_movements.user_id', '=', 'users.id')
            ->join('branch_products', 'branch_products.id', '=', 'stock_movements.branch_product_id')
            ->where('branch_products.branch_id', $branch->id)
            ->distinct()
            ->orderBy('users.name')
            ->get();
    }

    private function movementTypes(): array
    {
        return [
            ['id' => 'IN', 'label' => 'Entradas'],
            ['id' => 'OUT', 'label' => 'Salidas'],
            ['id' => 'ADJUSTMENT', 'label' => 'Ajustes'],
        ];
    }

    private function movementReasons(): array
    {
        return [
            ['id' => 'PURCHASE', 'label' => 'Compra'],
            ['id' => 'SALE', 'label' => 'Venta'],
            ['id' => 'DAMAGED', 'label' => 'Danado'],
            ['id' => 'EXPIRED', 'label' => 'Caducado'],
            ['id' => 'OTHER', 'label' => 'Otros'],
            ['id' => 'INVENTORY_DIFFERENCE', 'label' => 'Ajuste manual'],
        ];
    }

    private function reportBranches(Request $request)
    {
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesión.');

        $branches = $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name')
            ->get();

        abort_if($branches->isEmpty(), 403, 'No tienes sucursales habilitadas para consultar reportes.');

        return $branches;
    }
}
