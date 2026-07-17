<?php

namespace App\Http\Controllers\Audits;

use App\Exports\PhysicalCountAuditWorkbookExport;
use App\Exports\PhysicalCountReportExport;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\User;
use App\Services\PhysicalCountSnapshotService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountReportController extends Controller
{
    use AuthorizesBranchAccess;

    public function __construct(
        private PhysicalCountSnapshotService $snapshotService
    ) {
    }

    public function index(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para ver reportes de auditoria.');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, true);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);

        return Inertia::render('Inventory/Reports/PhysicalCountsReports', [
            'branch' => $branch,
            'branches' => $this->accessibleBranches($request),
            'filters' => $filters,
            'summary' => $payload['summary'],
            'audits' => $payload['audits']->map(fn ($audit) => [
                'id' => $audit->id,
                'name' => $audit->name,
                'folio' => $audit->folio,
                'status' => $audit->status,
                'started_at' => optional($audit->started_at)->toDateTimeString(),
                'participants' => $audit->participants
                    ->map(fn ($participant) => [
                        'id' => $participant->id,
                        'name' => $participant->name,
                    ])
                    ->values(),
            ])->values(),
            'users' => $this->availableAuditUsers(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'reportRows' => $payload['reportRows']->values(),
            'reportPagination' => $payload['reportPagination'],
            'userSummary' => $payload['userSummary']->values(),
            'categorySummary' => $payload['categorySummary']->values(),
            'branchSummary' => $payload['branchSummary']->values(),
            'auditSummary' => $payload['auditSummary']->values(),
            'topDifferences' => $payload['topDifferences']->values(),
            'filterLabels' => $filterLabels,
        ]);
    }

    public function exportExcel(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoria.');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, false);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);
        $branchLabel = $branch?->name ?? 'Todas las sucursales';
        $fileBranch = $branch?->slug ?? 'todas-las-sucursales';

        return Excel::download(
            new PhysicalCountAuditWorkbookExport($payload, $filters, $filterLabels, $branchLabel),
            'reporte-auditoria-' . $fileBranch . '-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoria.');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, false);
        $exportData = $this->buildExportData($payload, $filters['report_type']);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);
        $branchLabel = $branch?->name ?? 'Todas las sucursales';
        $fileBranch = $branch?->slug ?? 'todas-las-sucursales';

        $pdf = Pdf::loadView('pdf.physical-count-reports', [
            'branch' => $branch ?? (object) ['name' => $branchLabel],
            'filters' => $filters,
            'filterLabels' => $filterLabels,
            'summary' => $payload['summary'],
            'sectionTitle' => $exportData['title'],
            'headings' => $exportData['headings'],
            'rows' => $exportData['rows'],
        ])->setPaper('letter', 'landscape');

        return $pdf->download(
            'reporte-auditoria-' . $fileBranch . '-' . now()->format('Ymd_His') . '.pdf'
        );
    }

    private function buildReportPayload(Collection $branches, array $filters, bool $paginate): array
    {
        $branchIds = $branches->pluck('id')->values()->all();

        $audits = PhysicalCount::with(['branch', 'creator', 'participants:id,name'])
            ->whereIn('branch_id', $branchIds)
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

        $this->hydrateAuditSnapshots($audits);

        $snapshotRows = $this->snapshotService->buildProductRows($audits);
        if ($filters['category_id']) {
            $snapshotRows = $snapshotRows
                ->where('category_id', (int) $filters['category_id'])
                ->values();
        }

        $allowedBranchProductIds = $snapshotRows->pluck('branch_product_id')->unique()->values()->all();
        $auditIds = $audits->pluck('id');

        $entries = PhysicalCountEntry::with([
                'user:id,name',
                'productBatch:id,branch_product_id,lot_number,expiration_date',
                'branchProduct.product.category:id,name',
                'branchProduct.product.subcategory:id,name,category_id',
            ])
            ->whereIn('physical_count_id', $auditIds)
            ->when($filters['user_ids'] !== [], fn ($query) => $query->whereIn('user_id', $filters['user_ids']))
            ->when($filters['search'], function ($query, $search) {
                FlexibleSearch::apply($query, $search, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'scanned_code',
                        'notes',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($subQuery, 'branchProduct', [
                        'barcode',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($subQuery, 'branchProduct.product', [
                        'name',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($subQuery, 'user', [
                        'name',
                    ], $phrase, $terms);
                });
            })
            ->when($allowedBranchProductIds !== [], fn ($query) => $query->whereIn('branch_product_id', $allowedBranchProductIds))
            ->get();

        $auditsById = $audits->keyBy('id');
        $entries = $entries
            ->filter(function ($entry) use ($auditsById) {
                $audit = $auditsById->get($entry->physical_count_id);

                return ! $audit?->recapture_started_at
                    || $entry->created_at >= $audit->recapture_started_at;
            })
            ->values();

        $activeBranchProducts = BranchProduct::with([
                'product.category:id,name',
                'product.subcategory:id,name,category_id',
                'branch:id,name,slug',
            ])
            ->whereIn('branch_id', $branchIds)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->when($filters['category_id'], function ($query, $categoryId) {
                $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->get();

        $comparisonRows = collect($this->buildComparisonRows($audits, $entries, $snapshotRows))
            ->map(function ($row) {
                $row['row_type_label'] = 'Contado';
                $row['status_label'] = match ($row['status']) {
                    'missing' => 'Faltante',
                    'surplus' => 'Sobrante',
                    'matched' => 'Macheado',
                    default => 'Pendiente',
                };

                return $row;
            });

        $pendingRows = collect($this->buildPendingRows($snapshotRows, $comparisonRows->all(), $audits, $activeBranchProducts))
            ->map(function ($row) {
                $row['row_type_label'] = 'No encontrado';
                $row['status_label'] = 'Pendiente';

                return $row;
            });

        $reportRows = collect([...$comparisonRows->all(), ...$pendingRows->all()]);

        if ($filters['status'] !== '') {
            $reportRows = $reportRows->filter(function ($row) use ($filters) {
                return match ($filters['status']) {
                    'not_found' => $row['row_type'] === 'pending',
                    'missing' => $row['status'] === 'missing',
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

        $branchSummary = $reportRows
            ->groupBy('branch_name')
            ->map(function ($group, $branchName) {
                $countedProducts = $group->where('row_type', 'counted')->count();
                $pendingProducts = $group->where('row_type', 'pending')->count();
                $totalProducts = $group->count();

                return [
                    'branch_name' => $branchName ?: 'Sin sucursal',
                    'audits' => $group->pluck('physical_count_id')->filter()->unique()->count(),
                    'products' => $totalProducts,
                    'counted_products' => $countedProducts,
                    'pending_products' => $pendingProducts,
                    'missing_products' => $group->where('status', 'missing')->count(),
                    'surplus_products' => $group->where('status', 'surplus')->count(),
                    'matched_products' => $group->where('status', 'matched')->count(),
                    'advance' => $totalProducts > 0 ? round($countedProducts / $totalProducts, 4) : 0,
                    'difference_units' => (float) $group->sum(fn ($row) => (float) ($row['difference'] ?? 0)),
                    'absolute_difference_units' => (float) $group->sum(fn ($row) => abs((float) ($row['difference'] ?? 0))),
                ];
            })
            ->sortBy('branch_name')
            ->values();

        $auditSummary = $reportRows
            ->groupBy(fn ($row) => ($row['physical_count_id'] ?? 'sin-auditoria') . ':' . ($row['folio'] ?? 'Sin folio'))
            ->map(function ($group) {
                $first = $group->first();
                $countedProducts = $group->where('row_type', 'counted')->count();
                $totalProducts = $group->count();

                return [
                    'branch_name' => $first['branch_name'] ?? 'Sin sucursal',
                    'audit_name' => $first['audit_name'] ?? 'Sin auditoria',
                    'folio' => $first['folio'] ?? 'Sin folio',
                    'audit_date' => $first['audit_date'] ?? null,
                    'products' => $totalProducts,
                    'counted_products' => $countedProducts,
                    'pending_products' => $group->where('row_type', 'pending')->count(),
                    'missing_products' => $group->where('status', 'missing')->count(),
                    'surplus_products' => $group->where('status', 'surplus')->count(),
                    'matched_products' => $group->where('status', 'matched')->count(),
                    'advance' => $totalProducts > 0 ? round($countedProducts / $totalProducts, 4) : 0,
                    'absolute_difference_units' => (float) $group->sum(fn ($row) => abs((float) ($row['difference'] ?? 0))),
                ];
            })
            ->sortBy([
                ['branch_name', 'asc'],
                ['audit_date', 'desc'],
            ])
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
            'branchSummary' => $branchSummary,
            'auditSummary' => $auditSummary,
            'topDifferences' => $topDifferences,
            'entries' => $entries,
        ];
    }

    private function buildComparisonRows(Collection $audits, Collection $entries, ?Collection $snapshotRows = null): array
    {
        $auditsById = $audits->keyBy('id');
        $snapshotByKey = ($snapshotRows ?? collect())
            ->keyBy(fn ($row) => $row['physical_count_id'] . ':' . $row['branch_product_id']);

        return $entries
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id)
            ->map(function ($group, $groupKey) use ($auditsById, $snapshotByKey) {
                $first = $group->first();
                $audit = $auditsById->get($first->physical_count_id);
                $snapshot = $snapshotByKey->get($groupKey);
                $systemStock = (float) ($snapshot['system_stock'] ?? ($first->branchProduct?->stock ?? 0));
                $countedStock = (float) $group->sum('counted_quantity');
                $damagedStock = (float) $group->sum('damaged_quantity');
                $expiredStock = (float) $group->sum('expired_quantity');
                $newStock = max(0, $countedStock - $damagedStock - $expiredStock);
                $difference = $newStock - $systemStock;

                return [
                    'id' => $first->physical_count_id . '-' . $first->branch_product_id,
                    'row_type' => 'counted',
                    'status' => $difference < 0 ? 'missing' : ($difference > 0 ? 'surplus' : 'matched'),
                    'physical_count_id' => $first->physical_count_id,
                    'audit_name' => $audit?->name ?? 'Sin auditoria',
                    'folio' => $audit?->folio ?? 'Sin folio',
                    'audit_date' => optional($audit?->started_at)->toDateString(),
                    'branch_name' => $audit?->branch?->name ?? 'Sin sucursal',
                    'branch_product_id' => $first->branch_product_id,
                    'product_name' => $snapshot['product_name'] ?? $first->branchProduct?->product?->name ?? 'Sin producto',
                    'category_name' => $snapshot['category_name'] ?? $first->branchProduct?->product?->category?->name ?? 'Sin categoria',
                    'subcategory_name' => $snapshot['subcategory_name'] ?? $first->branchProduct?->product?->subcategory?->name ?? 'Sin subcategoria',
                    'scanned_code' => $first->scanned_code ?: ($snapshot['scanned_code'] ?? $first->branchProduct?->barcode ?? '-'),
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

    private function buildPendingRows(
        Collection $snapshotRows,
        array $comparisonRows,
        Collection $audits,
        ?Collection $activeBranchProducts = null
    ): array
    {
        if ($snapshotRows->isNotEmpty()) {
            $countedKeys = collect($comparisonRows)
                ->map(fn ($row) => $row['physical_count_id'] . ':' . $row['branch_product_id'])
                ->unique();

            return $snapshotRows
                ->reject(fn ($row) => $countedKeys->contains($row['physical_count_id'] . ':' . $row['branch_product_id']))
                ->map(fn ($row) => [
                    'id' => 'pending-' . $row['physical_count_id'] . '-' . $row['branch_product_id'],
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'physical_count_id' => $row['physical_count_id'],
                    'audit_name' => $row['audit_name'],
                    'folio' => $row['folio'],
                    'audit_date' => $row['audit_date'],
                    'branch_name' => $row['branch_name'] ?? 'Sin sucursal',
                    'branch_product_id' => $row['branch_product_id'],
                    'product_name' => $row['product_name'],
                    'category_name' => $row['category_name'],
                    'subcategory_name' => $row['subcategory_name'],
                    'scanned_code' => $row['scanned_code'],
                    'system_stock' => (float) $row['system_stock'],
                    'counted_stock' => 0,
                    'damaged_stock' => 0,
                    'expired_stock' => 0,
                    'difference' => null,
                    'participants' => [],
                    'last_entry_at' => null,
                ])
                ->values()
                ->all();
        }

        $countedIds = collect($comparisonRows)->pluck('branch_product_id')->unique();
        $firstAudit = $audits->first();

        return ($activeBranchProducts ?? collect())
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
                    'branch_name' => $branchProduct->branch?->name ?? 'Sin sucursal',
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

    private function hydrateAuditSnapshots(Collection $audits): void
    {
        $audits->load('snapshot.items');

        $audits
            ->filter(fn ($audit) => $audit->status === 'open' && $audit->snapshot === null)
            ->each(fn ($audit) => $this->snapshotService->ensureForAudit($audit));

        $audits->load('snapshot.items');
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
            'branches' => [
                'title' => 'Resumen por sucursal',
                'headings' => ['Sucursal', 'Auditorias', 'Productos', 'Contados', 'No encontrados', 'Macheados', 'Faltantes', 'Sobrantes', 'Avance', 'Dif. neta', 'Dif. absoluta'],
                'rows' => $payload['branchSummary']->map(fn ($row) => [
                    $row['branch_name'],
                    $row['audits'],
                    $row['products'],
                    $row['counted_products'],
                    $row['pending_products'],
                    $row['matched_products'],
                    $row['missing_products'],
                    $row['surplus_products'],
                    round(((float) $row['advance']) * 100, 2) . '%',
                    $row['difference_units'],
                    $row['absolute_difference_units'],
                ])->all(),
            ],
            'audits' => [
                'title' => 'Resumen por auditoria',
                'headings' => ['Sucursal', 'Auditoria', 'Folio', 'Fecha', 'Productos', 'Contados', 'No encontrados', 'Macheados', 'Faltantes', 'Sobrantes', 'Avance', 'Dif. absoluta'],
                'rows' => $payload['auditSummary']->map(fn ($row) => [
                    $row['branch_name'],
                    $row['audit_name'],
                    $row['folio'],
                    $row['audit_date'],
                    $row['products'],
                    $row['counted_products'],
                    $row['pending_products'],
                    $row['matched_products'],
                    $row['missing_products'],
                    $row['surplus_products'],
                    round(((float) $row['advance']) * 100, 2) . '%',
                    $row['absolute_difference_units'],
                ])->all(),
            ],
            'differences' => [
                'title' => 'Ranking de diferencias',
                'headings' => ['Auditoria', 'Producto', 'Categoria', 'Codigo', 'Sistema', 'Conteo', 'Diferencia', 'Resultado'],
                'rows' => $payload['topDifferences']->map(fn ($row) => [
                    trim(($row['branch_name'] ?? 'Sin sucursal') . ' / ' . $row['audit_name']),
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
                    'Sucursal',
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
                    $row['branch_name'] ?? 'Sin sucursal',
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

    private function normalizeFilters(Request $request, ?Branch $branch): array
    {
        $branchFilter = $branch?->slug ?? '';

        return [
            'branch' => $branchFilter,
            'physical_count_id' => $request->input('physical_count_id'),
            'user_id' => $request->input('user_id'),
            'user_ids' => $this->normalizeUserIds($request),
            'user_scope' => $request->input('user_scope', 'participants'),
            'category_id' => $request->input('category_id'),
            'report_date' => $request->input('report_date'),
            'date_scope' => $request->input('date_scope', 'day'),
            'status' => $request->input('status', ''),
            'search' => trim((string) $request->input('search', '')),
            'report_type' => $request->input('report_type', 'summary'),
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => TablePagination::resolvePerPage($request, 25),
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
        $selectedUsers = $filters['user_ids'] !== []
            ? User::query()->whereIn('id', $filters['user_ids'])->orderBy('name')->get(['id', 'name'])
            : collect();
        $selectedCategory = $filters['category_id']
            ? Category::query()->find($filters['category_id'], ['id', 'name'])
            : null;

        return [
            'audit' => $selectedAudit
                ? trim(($selectedAudit->name ?: 'Auditoria') . ' - ' . ($selectedAudit->folio ?: 'Sin folio'))
                : 'Todas',
            'user' => $selectedUsers->isNotEmpty() ? $selectedUsers->pluck('name')->join(', ') : 'Todos',
            'category' => $selectedCategory?->name ?: 'Todas',
            'status' => match ($filters['status']) {
                'matched' => 'Macheado',
                'missing' => 'Faltantes',
                'surplus' => 'Sobrantes',
                'not_found' => 'No encontrado',
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

    private function resolveReportBranches(Request $request): Collection
    {
        $branchSlug = $request->query('branch');
        $branches = $this->accessibleBranches($request);

        if (! $branchSlug || $branchSlug === 'all') {
            return $branches;
        }

        $branch = $branches->firstWhere('slug', $branchSlug);
        abort_unless($branch, 403, 'No tienes acceso a esta sucursal.');

        return collect([$branch]);
    }

    private function accessibleBranches(Request $request): Collection
    {
        /** @var User|null $user */
        $user = $request->user()?->loadMissing(['role', 'branches']);

        abort_unless($user, 401, 'Debes iniciar sesion.');

        return $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name')
            ->get();
    }

    private function normalizeUserIds(Request $request): array
    {
        $userIds = $request->input('user_ids', []);

        if (is_string($userIds)) {
            $userIds = preg_split('/,/', $userIds) ?: [];
        }

        if (! is_array($userIds)) {
            $userIds = [];
        }

        if ($request->filled('user_id')) {
            $userIds[] = $request->input('user_id');
        }

        return collect($userIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function canViewReports(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.reports');
    }
}
