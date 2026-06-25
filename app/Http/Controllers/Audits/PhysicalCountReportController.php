<?php

namespace App\Http\Controllers\Audits;

use App\Exports\PhysicalCountReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountReportController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para ver reportes de auditoria.');

        $branch = $this->resolveBranch($request->query('branch'));
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branch, $filters, true);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);

        return Inertia::render('Audits/PhysicalCounts/Reports', [
            'branch' => $branch,
            'branches' => Branch::where('active', true)
                ->select('id', 'name', 'slug', 'color')
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
            'summary' => $payload['summary'],
            'audits' => $payload['audits']->map(fn ($audit) => [
                'id' => $audit->id,
                'name' => $audit->name,
                'folio' => $audit->folio,
                'status' => $audit->status,
                'started_at' => optional($audit->started_at)->toDateTimeString(),
            ])->values(),
            'users' => $this->availableAuditUsers(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'reportRows' => $payload['reportRows']->values(),
            'reportPagination' => $payload['reportPagination'],
            'userSummary' => $payload['userSummary']->values(),
            'categorySummary' => $payload['categorySummary']->values(),
            'topDifferences' => $payload['topDifferences']->values(),
            'filterLabels' => $filterLabels,
        ]);
    }

    public function exportExcel(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoria.');

        $branch = $this->resolveBranch($request->query('branch'));
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branch, $filters, false);
        $exportData = $this->buildExportData($payload, $filters['report_type']);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);

        return Excel::download(
            new PhysicalCountReportExport($exportData['headings'], $exportData['rows']),
            'reporte-auditoria-' . $branch->slug . '-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoria.');

        $branch = $this->resolveBranch($request->query('branch'));
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branch, $filters, false);
        $exportData = $this->buildExportData($payload, $filters['report_type']);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);

        $pdf = Pdf::loadView('pdf.physical-count-reports', [
            'branch' => $branch,
            'filters' => $filters,
            'filterLabels' => $filterLabels,
            'summary' => $payload['summary'],
            'sectionTitle' => $exportData['title'],
            'headings' => $exportData['headings'],
            'rows' => $exportData['rows'],
        ])->setPaper('letter', 'landscape');

        return $pdf->download(
            'reporte-auditoria-' . $branch->slug . '-' . now()->format('Ymd_His') . '.pdf'
        );
    }

    private function buildReportPayload(Branch $branch, array $filters, bool $paginate): array
    {
        $audits = PhysicalCount::with(['branch', 'creator', 'participants:id,name'])
            ->where('branch_id', $branch->id)
            ->when($filters['physical_count_id'], fn ($query, $id) => $query->where('id', $id))
            ->when($filters['report_date'], function ($query) use ($filters) {
                return match ($filters['date_scope']) {
                    'year' => $query->whereYear('started_at', substr($filters['report_date'], 0, 4)),
                    'month' => $query
                        ->whereYear('started_at', substr($filters['report_date'], 0, 4))
                        ->whereMonth('started_at', substr($filters['report_date'], 5, 2)),
                    default => $query->whereDate('started_at', $filters['report_date']),
                };
            })
            ->latest()
            ->get();

        $auditIds = $audits->pluck('id');

        $entries = PhysicalCountEntry::with([
                'user:id,name',
                'productBatch:id,branch_product_id,lot_number,expiration_date',
                'branchProduct.product.category:id,name',
                'branchProduct.product.subcategory:id,name,category_id',
            ])
            ->whereIn('physical_count_id', $auditIds)
            ->when($filters['user_id'], fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['search'], function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('scanned_code', 'LIKE', "%{$search}%")
                        ->orWhere('notes', 'LIKE', "%{$search}%")
                        ->orWhereHas('branchProduct.product', fn ($productQuery) => $productQuery->where('name', 'LIKE', "%{$search}%"))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'LIKE', "%{$search}%"));
                });
            })
            ->get();

        $activeBranchProducts = BranchProduct::with([
                'product.category:id,name',
                'product.subcategory:id,name,category_id',
            ])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->when($filters['category_id'], function ($query, $categoryId) {
                $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->get();

        $comparisonRows = collect($this->buildComparisonRows($audits, $entries, $activeBranchProducts->pluck('id')->all()))
            ->map(function ($row) {
                $row['row_type_label'] = 'Contado';
                $row['status_label'] = match ($row['status']) {
                    'missing' => 'Faltante',
                    'surplus' => 'Sobrante',
                    'matched' => 'Correcto',
                    default => 'Pendiente',
                };

                return $row;
            });

        $pendingRows = collect($this->buildPendingRows($activeBranchProducts, $comparisonRows->all(), $audits))
            ->map(function ($row) {
                $row['row_type_label'] = 'No encontrado';
                $row['status_label'] = 'Pendiente';

                return $row;
            });

        $reportRows = collect([...$comparisonRows->all(), ...$pendingRows->all()]);

        if ($filters['status'] !== '') {
            $reportRows = $reportRows->filter(function ($row) use ($filters) {
                return match ($filters['status']) {
                    'found', 'counted' => $row['row_type'] === 'counted',
                    'not_found' => $row['row_type'] === 'pending',
                    'missing' => $row['status'] === 'missing',
                    'not_missing' => in_array($row['status'], ['matched', 'surplus'], true),
                    'surplus' => $row['status'] === 'surplus',
                    'matched' => $row['status'] === 'matched',
                    default => true,
                };
            })->values();
        }

        if ($filters['search'] !== '') {
            $search = mb_strtolower($filters['search']);
            $reportRows = $reportRows->filter(function ($row) use ($search) {
                return collect([
                    $row['product_name'] ?? '',
                    $row['category_name'] ?? '',
                    $row['subcategory_name'] ?? '',
                    $row['scanned_code'] ?? '',
                    implode(', ', $row['participants'] ?? []),
                    $row['folio'] ?? '',
                    $row['audit_name'] ?? '',
                ])->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $search));
            })->values();
        }

        $userSummary = $entries
            ->groupBy('user_id')
            ->map(function ($group) {
                $userName = $group->first()?->user?->name ?? 'Sin usuario';
                $counted = (float) $group->sum('counted_quantity');
                $damaged = (float) $group->sum('damaged_quantity');
                $expired = (float) $group->sum('expired_quantity');

                return [
                    'user_name' => $userName,
                    'records' => $group->count(),
                    'products' => $group->pluck('branch_product_id')->unique()->count(),
                    'counted_stock' => $counted,
                    'damaged_stock' => $damaged,
                    'expired_stock' => $expired,
                ];
            })
            ->sortByDesc('records')
            ->values();

        $categorySummary = $reportRows
            ->groupBy('category_name')
            ->map(function ($group, $categoryName) {
                return [
                    'category_name' => $categoryName ?: 'Sin categoria',
                    'products' => $group->count(),
                    'counted_products' => $group->where('row_type', 'counted')->count(),
                    'pending_products' => $group->where('row_type', 'pending')->count(),
                    'missing_products' => $group->where('status', 'missing')->count(),
                    'surplus_products' => $group->where('status', 'surplus')->count(),
                    'matched_products' => $group->where('status', 'matched')->count(),
                ];
            })
            ->sortByDesc('products')
            ->values();

        $topDifferences = $reportRows
            ->where('row_type', 'counted')
            ->map(function ($row) {
                $row['absolute_difference'] = abs((float) ($row['difference'] ?? 0));
                return $row;
            })
            ->sortByDesc('absolute_difference')
            ->take(10)
            ->values();

        $summary = [
            'audits' => $audits->count(),
            'records' => $entries->count(),
            'participants' => $entries->pluck('user_id')->filter()->unique()->count(),
            'counted_products' => $reportRows->where('row_type', 'counted')->count(),
            'pending_products' => $reportRows->where('row_type', 'pending')->count(),
            'missing_products' => $reportRows->where('status', 'missing')->count(),
            'surplus_products' => $reportRows->where('status', 'surplus')->count(),
            'matched_products' => $reportRows->where('status', 'matched')->count(),
        ];

        $reportPagination = null;
        if ($paginate) {
            $perPage = $filters['per_page'];
            $currentPage = max(1, (int) $filters['page']);
            $paginated = new LengthAwarePaginator(
                $reportRows->forPage($currentPage, $perPage)->values(),
                $reportRows->count(),
                $perPage,
                $currentPage,
                [
                    'path' => route('audits.physical-counts.reports'),
                    'query' => collect($filters)->except('page')->all(),
                ]
            );

            $reportRows = collect($paginated->items());
            $reportPagination = $paginated->toArray();
        }

        return [
            'audits' => $audits,
            'reportRows' => $reportRows->values(),
            'reportPagination' => $reportPagination,
            'summary' => $summary,
            'userSummary' => $userSummary,
            'categorySummary' => $categorySummary,
            'topDifferences' => $topDifferences,
        ];
    }

    private function buildComparisonRows(Collection $audits, Collection $entries, ?array $allowedBranchProductIds = null): array
    {
        $auditsById = $audits->keyBy('id');

        return $entries
            ->when($allowedBranchProductIds !== null, function ($collection) use ($allowedBranchProductIds) {
                return $collection->whereIn('branch_product_id', $allowedBranchProductIds);
            })
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id)
            ->map(function ($group) use ($auditsById) {
                $first = $group->first();
                $audit = $auditsById->get($first->physical_count_id);
                $systemStock = (float) ($first->branchProduct?->stock ?? 0);
                $countedStock = (float) $group->sum('counted_quantity');
                $damagedStock = (float) $group->sum('damaged_quantity');
                $expiredStock = (float) $group->sum('expired_quantity');
                $sellableStock = max(0, $countedStock - $damagedStock - $expiredStock);
                $difference = $sellableStock - $systemStock;

                return [
                    'id' => $first->physical_count_id . '-' . $first->branch_product_id,
                    'row_type' => 'counted',
                    'status' => $difference < 0 ? 'missing' : ($difference > 0 ? 'surplus' : 'matched'),
                    'physical_count_id' => $first->physical_count_id,
                    'audit_name' => $audit?->name ?? 'Sin auditoria',
                    'folio' => $audit?->folio ?? 'Sin folio',
                    'audit_date' => optional($audit?->started_at)->toDateString(),
                    'branch_product_id' => $first->branch_product_id,
                    'product_name' => $first->branchProduct?->product?->name ?? 'Sin producto',
                    'category_name' => $first->branchProduct?->product?->category?->name ?? 'Sin categoria',
                    'subcategory_name' => $first->branchProduct?->product?->subcategory?->name ?? 'Sin subcategoria',
                    'scanned_code' => $first->scanned_code ?: ($first->branchProduct?->barcode ?? '-'),
                    'system_stock' => $systemStock,
                    'counted_stock' => $countedStock,
                    'damaged_stock' => $damagedStock,
                    'expired_stock' => $expiredStock,
                    'difference' => $difference,
                    'participants' => $group->pluck('user.name')->filter()->unique()->values()->all(),
                    'last_entry_at' => optional($group->sortByDesc('created_at')->first()?->created_at)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildPendingRows(Collection $activeBranchProducts, array $comparisonRows, Collection $audits): array
    {
        $countedIds = collect($comparisonRows)->pluck('branch_product_id')->unique();
        $firstAudit = $audits->first();

        return $activeBranchProducts
            ->reject(fn ($branchProduct) => $countedIds->contains($branchProduct->id))
            ->map(function ($branchProduct) use ($firstAudit) {
                return [
                    'id' => 'pending-' . $branchProduct->id,
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'physical_count_id' => null,
                    'audit_name' => $firstAudit?->name ?? 'Sin auditoria filtrada',
                    'folio' => $firstAudit?->folio ?? 'Sin folio',
                    'audit_date' => optional($firstAudit?->started_at)->toDateString(),
                    'branch_product_id' => $branchProduct->id,
                    'product_name' => $branchProduct->product?->name ?? 'Sin producto',
                    'category_name' => $branchProduct->product?->category?->name ?? 'Sin categoria',
                    'subcategory_name' => $branchProduct->product?->subcategory?->name ?? 'Sin subcategoria',
                    'scanned_code' => $branchProduct->barcode ?? '-',
                    'system_stock' => (float) ($branchProduct->stock ?? 0),
                    'counted_stock' => 0,
                    'damaged_stock' => 0,
                    'expired_stock' => 0,
                    'difference' => null,
                    'participants' => [],
                    'last_entry_at' => null,
                ];
            })
            ->values()
            ->all();
    }

    private function buildExportData(array $payload, string $reportType): array
    {
        return match ($reportType) {
            'users' => [
                'title' => 'Resumen por usuario',
                'headings' => ['Usuario', 'Registros', 'Productos', 'Contado', 'Danado', 'Caducado'],
                'rows' => $payload['userSummary']->map(fn ($row) => [
                    $row['user_name'],
                    $row['records'],
                    $row['products'],
                    $row['counted_stock'],
                    $row['damaged_stock'],
                    $row['expired_stock'],
                ])->all(),
            ],
            'categories' => [
                'title' => 'Resumen por categoria',
                'headings' => ['Categoria', 'Productos', 'Contados', 'Pendientes', 'Faltantes', 'Sobrantes', 'Correctos'],
                'rows' => $payload['categorySummary']->map(fn ($row) => [
                    $row['category_name'],
                    $row['products'],
                    $row['counted_products'],
                    $row['pending_products'],
                    $row['missing_products'],
                    $row['surplus_products'],
                    $row['matched_products'],
                ])->all(),
            ],
            'differences' => [
                'title' => 'Ranking de diferencias',
                'headings' => ['Auditoria', 'Producto', 'Categoria', 'Codigo', 'Sistema', 'Conteo', 'Diferencia', 'Resultado'],
                'rows' => $payload['topDifferences']->map(fn ($row) => [
                    $row['audit_name'],
                    $row['product_name'],
                    $row['category_name'],
                    $row['scanned_code'],
                    $row['system_stock'],
                    $row['counted_stock'],
                    $row['difference'],
                    $row['status_label'],
                ])->all(),
            ],
            'summary' => [
                'title' => 'Resumen general',
                'headings' => ['Indicador', 'Valor'],
                'rows' => [
                    ['Auditorias', $payload['summary']['audits'] ?? 0],
                    ['Registros', $payload['summary']['records'] ?? 0],
                    ['Usuarios', $payload['summary']['participants'] ?? 0],
                    ['Contados', $payload['summary']['counted_products'] ?? 0],
                    ['No encontrados', $payload['summary']['pending_products'] ?? 0],
                    ['Faltantes', $payload['summary']['missing_products'] ?? 0],
                    ['Sobrantes', $payload['summary']['surplus_products'] ?? 0],
                    ['Correctos', $payload['summary']['matched_products'] ?? 0],
                ],
            ],
            default => [
                'title' => 'Detalle completo',
                'headings' => [
                    'Auditoria',
                    'Folio',
                    'Fecha',
                    'Tipo',
                    'Resultado',
                    'Producto',
                    'Categoria',
                    'Subcategoria',
                    'Codigo',
                    'Stock sistema',
                    'Conteo fisico',
                    'Danado',
                    'Caducado',
                    'Diferencia',
                    'Usuarios',
                ],
                'rows' => $payload['reportRows']->map(fn ($row) => [
                    $row['audit_name'],
                    $row['folio'],
                    $row['audit_date'],
                    $row['row_type_label'],
                    $row['status_label'],
                    $row['product_name'],
                    $row['category_name'],
                    $row['subcategory_name'],
                    $row['scanned_code'],
                    $row['system_stock'],
                    $row['counted_stock'],
                    $row['damaged_stock'],
                    $row['expired_stock'],
                    $row['difference'] ?? '-',
                    implode(', ', $row['participants'] ?? []),
                ])->all(),
            ],
        };
    }

    private function normalizeFilters(Request $request, Branch $branch): array
    {
        return [
            'branch' => $branch->slug,
            'physical_count_id' => $request->input('physical_count_id'),
            'user_id' => $request->input('user_id'),
            'category_id' => $request->input('category_id'),
            'report_date' => $request->input('report_date'),
            'date_scope' => $request->input('date_scope', 'day'),
            'status' => $request->input('status', ''),
            'search' => trim((string) $request->input('search', '')),
            'report_type' => $request->input('report_type', 'summary'),
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => min(100, max(10, (int) $request->input('per_page', 25))),
        ];
    }

    private function availableAuditUsers(): Collection
    {
        return User::with('role:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name ?? 'Sin rol',
            ])
            ->values();
    }

    private function buildFilterLabels(array $filters, Collection $audits): array
    {
        $selectedAudit = $audits->firstWhere('id', (int) $filters['physical_count_id']);
        $selectedUser = $filters['user_id']
            ? User::query()->find($filters['user_id'], ['id', 'name'])
            : null;
        $selectedCategory = $filters['category_id']
            ? Category::query()->find($filters['category_id'], ['id', 'name'])
            : null;

        return [
            'audit' => $selectedAudit
                ? trim(($selectedAudit->name ?: 'Auditoria') . ' - ' . ($selectedAudit->folio ?: 'Sin folio'))
                : 'Todas',
            'user' => $selectedUser?->name ?: 'Todos',
            'category' => $selectedCategory?->name ?: 'Todas',
            'status' => match ($filters['status']) {
                'found' => 'Encontrados',
                'not_found' => 'No encontrados',
                'counted' => 'Contados',
                'missing' => 'Faltantes',
                'not_missing' => 'No faltantes',
                'surplus' => 'Sobrantes',
                'matched' => 'Correctos',
                default => 'Todos',
            },
            'date_scope' => match ($filters['date_scope']) {
                'year' => 'Por ano',
                'month' => 'Por mes',
                default => 'Por dia',
            },
            'report_date' => $filters['report_date'] ?: 'Sin fecha',
            'search' => $filters['search'] ?: 'Sin filtro',
        ];
    }

    private function resolveBranch(?string $branchSlug): Branch
    {
        if (!$branchSlug) {
            return Branch::where('active', true)
                ->orderBy('name')
                ->select('id', 'name', 'slug', 'color')
                ->firstOrFail();
        }

        return Branch::where('active', true)
            ->where('slug', $branchSlug)
            ->select('id', 'name', 'slug', 'color')
            ->firstOrFail();
    }

    private function canViewReports(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.reports');
    }
}
