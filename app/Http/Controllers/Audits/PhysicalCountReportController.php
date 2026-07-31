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
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para ver reportes de auditoría.');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, true);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);
        $auditOptions = PhysicalCount::with(['participants:id,name'])
            ->whereIn('branch_id', $branches->pluck('id'))
            ->when($filters['search'], fn ($query, $search) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('folio', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate($filters['per_page'])
            ->withQueryString();
        $auditOptions->setCollection($auditOptions->getCollection()->map(fn ($audit) => [
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
        ]));

        return Inertia::render('Inventory/Reports/PhysicalCountsReports', [
            'branch' => $branch,
            'branches' => $this->accessibleBranches($request),
            'filters' => $filters,
            'summary' => $payload['summary'],
            'audits' => $auditOptions->getCollection()->values(),
            'auditPagination' => [
                'current_page' => $auditOptions->currentPage(),
                'last_page' => $auditOptions->lastPage(),
                'per_page' => $auditOptions->perPage(),
                'total' => $auditOptions->total(),
                'path' => $auditOptions->path(),
                'first_page_url' => $auditOptions->url(1),
                'prev_page_url' => $auditOptions->previousPageUrl(),
                'next_page_url' => $auditOptions->nextPageUrl(),
                'links' => $auditOptions->linkCollection(),
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'reportRows' => $payload['reportRows']->values(),
            'reportPagination' => $payload['reportPagination'],
            'userSummary' => $payload['userSummary']->values(),
            'categorySummary' => $payload['categorySummary']->values(),
            'branchSummary' => $payload['branchSummary']->values(),
            'auditSummary' => $payload['auditSummary']->values(),
            'roundSummary' => $payload['roundSummary']->values(),
            'topDifferences' => $payload['topDifferences']->values(),
            'filterLabels' => $filterLabels,
        ]);
    }

    public function exportExcel(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoría.');

        // El libro contiene varias hojas, gráficas y resúmenes; ampliamos el
        // margen solo durante esta descarga para evitar que el navegador aborte
        // la petición en auditorías grandes.
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, false);
        $filterLabels = $this->buildFilterLabels($filters, $payload['audits']);
        $filterLabels['generated_by'] = $request->user()?->name ?? 'Usuario autenticado';
        $branchLabel = $branch?->name ?? 'Todas las sucursales';
        $fileBranch = $branch?->slug ?? 'todas-las-sucursales';

        return Excel::download(
            new PhysicalCountAuditWorkbookExport($payload, $filters, $filterLabels, $branchLabel),
            'reporte-auditoria-' . $fileBranch . '-' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoría.');

        $branches = $this->resolveReportBranches($request);
        $branch = $branches->count() === 1 ? $branches->first() : null;
        $filters = $this->normalizeFilters($request, $branch);
        $payload = $this->buildReportPayload($branches, $filters, false);
        $exportData = $this->buildExportData($payload, 'detail');
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
            'detailRows' => $payload['reportRows']->values(),
        ])->setPaper('letter', 'landscape');

        return $pdf->download(
            'reporte-auditoria-' . $fileBranch . '-' . now()->format('Ymd_His') . '.pdf'
        );
    }

    private function buildReportPayload(Collection $branches, array $filters, bool $paginate): array
    {
        $branchIds = $branches->pluck('id')->values()->all();

        $audits = PhysicalCount::with(['branch', 'creator', 'participants:id,name', 'rounds.opener:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->when($filters['audit_ids'] !== [], fn ($query) => $query->whereIn('id', $filters['audit_ids']))
            ->when($filters['search'], fn ($query, $search) => $query->where(function ($nested) use ($search) {
                $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('folio', 'like', "%{$search}%");
            }))
            ->latest()
            ->get();

        $this->hydrateAuditSnapshots($audits);

        $snapshotRows = $this->snapshotService->buildProductRows($audits);
        $snapshotRows = $snapshotRows->filter(function ($row) use ($filters) {
            $selected = collect($filters['audit_filters'][$row['physical_count_id']]['category_ids'] ?? [])
                ->map(fn ($id) => (int) $id);
            return $selected->isEmpty() || $selected->contains((int) $row['category_id']);
        })->values();

        $allowedBranchProductIds = $snapshotRows->pluck('branch_product_id')->unique()->values()->all();
        $auditIds = $audits->pluck('id');

        $allEntries = PhysicalCountEntry::with([
                'user:id,name',
                'round:id,physical_count_id,round_number,type,scope,opened_by,started_at,closed_at,applied_at',
                'productBatch:id,branch_product_id,lot_number,expiration_date',
                'branchProduct.product.category:id,name',
                'branchProduct.product.subcategory:id,name,category_id',
                'branchProduct.product.barcodes:id,product_id,code',
            ])
            ->whereIn('physical_count_id', $auditIds)
            ->when($allowedBranchProductIds !== [], fn ($query) => $query->whereIn('branch_product_id', $allowedBranchProductIds))
            ->get();

        $allEntries = $allEntries->filter(function ($entry) use ($filters) {
            $configuration = $filters['audit_filters'][$entry->physical_count_id] ?? [];
            $userIds = collect($configuration['user_ids'] ?? [])->map(fn ($id) => (int) $id);
            $categoryIds = collect($configuration['category_ids'] ?? [])->map(fn ($id) => (int) $id);

            return ($userIds->isEmpty() || $userIds->contains((int) $entry->user_id))
                && ($categoryIds->isEmpty()
                    || $categoryIds->contains((int) $entry->branchProduct?->product?->category_id));
        })->values();

        $entries = $this->consolidateFinalEntries($allEntries);

        $activeBranchProducts = BranchProduct::with([
                'product.category:id,name',
                'product.subcategory:id,name,category_id',
                'product.barcodes:id,product_id,code',
                'branch:id,name,slug',
            ])
            ->whereIn('branch_id', $branchIds)
            ->whereIn('status', [
                BranchProduct::STATUS_ACTIVE,
                BranchProduct::STATUS_SEASONAL,
            ])
            ->get();

        $productCodesByBranchProduct = $activeBranchProducts->mapWithKeys(function ($branchProduct) {
            $codes = collect([$branchProduct->barcode])
                ->merge($branchProduct->product?->barcodes?->pluck('code') ?? [])
                ->map(fn ($code) => trim((string) $code))
                ->filter()
                ->unique()
                ->values()
                ->all();

            return [$branchProduct->id => $codes];
        });

        $comparisonRows = collect($this->buildComparisonRows($audits, $entries, $snapshotRows))
            ->map(function ($row) {
                $row['row_type_label'] = 'Contado';
                $row['status_label'] = match ($row['status']) {
                    'missing' => 'Faltante',
                    'surplus' => 'Sobrante',
                    'matched' => 'Coincidente',
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

        $reportRows = collect([...$comparisonRows->all(), ...$pendingRows->all()])
            ->map(function (array $row) use ($productCodesByBranchProduct) {
                $codes = $productCodesByBranchProduct->get(
                    $row['branch_product_id'] ?? null,
                    $row['product_codes'] ?? []
                );
                $row['product_codes'] = collect([
                    $row['scanned_code'] ?? null,
                    ...$codes,
                ])->filter()->unique()->values()->all();

                return $row;
            });
        $reportRows = $reportRows->filter(function ($row) use ($filters) {
            $results = collect($filters['audit_filters'][$row['physical_count_id']]['results'] ?? []);
            if ($results->isEmpty()) return true;

            $result = ($row['row_type'] ?? null) === 'pending'
                ? 'not_found'
                : ($row['status'] ?? null);
            return $results->contains($result);
        })->values();

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
                    'audit_name' => $first['audit_name'] ?? 'Sin auditoría',
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
            'total_products' => $reportRows->count(),
            'counted_products' => $reportRows->where('row_type', 'counted')->count(),
            'pending_products' => $reportRows->where('row_type', 'pending')->count(),
            'missing_products' => $reportRows->where('status', 'missing')->count(),
            'surplus_products' => $reportRows->where('status', 'surplus')->count(),
            'matched_products' => $reportRows->where('status', 'matched')->count(),
        ];
        $summary['advance'] = $summary['total_products'] > 0
            ? $summary['counted_products'] / $summary['total_products']
            : 0;
        $summary['accuracy'] = $summary['counted_products'] > 0
            ? $summary['matched_products'] / $summary['counted_products']
            : 0;

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
            'allEntries' => $allEntries->values(),
            'rounds' => $audits->flatMap(fn ($audit) => $audit->rounds)->values(),
            'roundSummary' => $audits->flatMap(fn ($audit) => $audit->rounds->map(fn ($round) => [
                'id' => $round->id,
                'branch_name' => $audit->branch?->name ?? 'Sin sucursal',
                'audit_name' => $audit->name,
                'folio' => $audit->folio,
                'round_number' => $round->round_number,
                'type_label' => $round->type === 'original' ? 'Original' : 'Reapertura',
                'scope_label' => $round->scope === 'zero_stock' ? 'Solo stock cero' : 'Todos los productos',
                'opened_by' => $round->opener?->name ?? 'Sin usuario',
                'started_at' => optional($round->started_at)->toDateTimeString(),
                'closed_at' => optional($round->closed_at)->toDateTimeString(),
            ]))->values(),
        ];
    }

    private function consolidateFinalEntries(Collection $entries): Collection
    {
        return $entries
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id)
            ->flatMap(function (Collection $productEntries) {
                $latestRoundNumber = $productEntries
                    ->max(fn ($entry) => (int) ($entry->round?->round_number ?? 1));

                return $productEntries->filter(
                    fn ($entry) => (int) ($entry->round?->round_number ?? 1) === $latestRoundNumber
                );
            })
            ->values();
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
                    'audit_name' => $audit?->name ?? 'Sin auditoría',
                    'folio' => $audit?->folio ?? 'Sin folio',
                    'audit_date' => optional($audit?->started_at)->toDateString(),
                    'branch_name' => $audit?->branch?->name ?? 'Sin sucursal',
                    'branch_product_id' => $first->branch_product_id,
                    'product_name' => $snapshot['product_name'] ?? $first->branchProduct?->product?->name ?? 'Sin producto',
                    'category_name' => $snapshot['category_name'] ?? $first->branchProduct?->product?->category?->name ?? 'Sin categoria',
                    'subcategory_name' => $snapshot['subcategory_name'] ?? $first->branchProduct?->product?->subcategory?->name ?? 'Sin subcategoria',
                    'scanned_code' => $first->scanned_code ?: ($snapshot['scanned_code'] ?? $first->branchProduct?->barcode ?? '-'),
                    'product_codes' => $first->branchProduct?->product?->barcodes?->pluck('code')->values()->all() ?? [],
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
                    'product_codes' => $row['product_codes'] ?? [],
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
                    'audit_name' => $firstAudit?->name ?? 'Sin auditoría filtrada',
                    'folio' => $firstAudit?->folio ?? 'Sin folio',
                    'audit_date' => optional($firstAudit?->started_at)->toDateString(),
                    'branch_name' => $branchProduct->branch?->name ?? 'Sin sucursal',
                    'branch_product_id' => $branchProduct->id,
                    'product_name' => $branchProduct->product?->name ?? 'Sin producto',
                    'category_name' => $branchProduct->product?->category?->name ?? 'Sin categoria',
                    'subcategory_name' => $branchProduct->product?->subcategory?->name ?? 'Sin subcategoria',
                    'scanned_code' => $branchProduct->barcode ?? '-',
                    'product_codes' => $branchProduct->product?->barcodes?->pluck('code')->values()->all() ?? [],
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
            'rounds' => [
                'title' => 'Historial de rondas',
                'headings' => ['Sucursal', 'Auditoría', 'Folio', 'Ronda', 'Tipo', 'Alcance', 'Abierta por', 'Inicio', 'Cierre'],
                'rows' => $payload['roundSummary']->map(fn ($row) => [
                    $row['branch_name'],
                    $row['audit_name'],
                    $row['folio'],
                    $row['round_number'],
                    $row['type_label'],
                    $row['scope_label'],
                    $row['opened_by'],
                    $row['started_at'],
                    $row['closed_at'] ?? 'Abierta',
                ])->all(),
            ],
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
                'headings' => ['Categoría', 'Productos', 'Contados', 'Pendientes', 'Faltantes', 'Sobrantes', 'Correctos'],
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
                'headings' => ['Sucursal', 'Auditorías', 'Productos', 'Contados', 'No encontrados', 'Coincidentes', 'Faltantes', 'Sobrantes', 'Avance', 'Dif. neta', 'Dif. absoluta'],
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
                'title' => 'Resumen por auditoría',
                'headings' => ['Sucursal', 'Auditoría', 'Folio', 'Fecha', 'Productos', 'Contados', 'No encontrados', 'Coincidentes', 'Faltantes', 'Sobrantes', 'Avance', 'Dif. absoluta'],
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
                'headings' => ['Auditoría', 'Producto', 'Categoría', 'Código', 'Sistema', 'Conteo', 'Diferencia', 'Resultado'],
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
                    ['Auditorías', $payload['summary']['audits'] ?? 0],
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
                    'Categoría',
                    'Subcategoria',
                    'Código',
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
            'report_date' => null,
            'search' => trim((string) $request->input('search', '')),
            'audit_ids' => collect($request->input('audit_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'audit_filters' => $this->normalizeAuditFilters($request),
            'selected_results' => collect($this->normalizeAuditFilters($request))
                ->flatMap(fn ($configuration) => $configuration['results'] ?? [])
                ->unique()
                ->values()
                ->all(),
            'report_type' => 'summary',
            'page' => max(1, (int) $request->input('page', 1)),
            'per_page' => TablePagination::resolvePerPage($request, 25),
        ];
    }

    private function normalizeAuditFilters(Request $request): array
    {
        $filters = $request->input('audit_filters', []);
        if (is_string($filters)) {
            $filters = json_decode($filters, true) ?: [];
        }
        if (! is_array($filters)) return [];

        return collect($filters)->mapWithKeys(function ($configuration, $auditId) {
            $normalizeIds = fn ($values) => collect(is_array($values) ? $values : [])
                ->map(fn ($value) => (int) $value)
                ->filter()
                ->unique()
                ->values()
                ->all();
            $results = collect(is_array($configuration['results'] ?? null) ? $configuration['results'] : [])
                ->filter(fn ($value) => in_array($value, ['matched', 'missing', 'surplus', 'not_found'], true))
                ->unique()
                ->values()
                ->all();

            return [(int) $auditId => [
                'user_ids' => $normalizeIds($configuration['user_ids'] ?? []),
                'category_ids' => $normalizeIds($configuration['category_ids'] ?? []),
                'results' => $results,
                'include_lots' => filter_var(
                    $configuration['include_lots'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]];
        })->all();
    }

    private function buildFilterLabels(array $filters, Collection $audits): array
    {
        return [
            'audit' => $audits->count() === 1
                ? trim(($audits->first()->name ?: 'Auditoría').' - '.($audits->first()->folio ?: 'Sin folio'))
                : 'Todas las auditorías filtradas',
            'user' => 'Configuración individual por auditoría',
            'category' => 'Configuración individual por auditoría',
            'status' => empty($filters['selected_results'])
                ? 'Reporte general'
                : collect($filters['selected_results'])->map(fn ($result) => match ($result) {
                    'matched' => 'Coincidentes',
                    'missing' => 'Faltantes',
                    'surplus' => 'Sobrantes',
                    'not_found' => 'No encontrados',
                    default => $result,
                })->join(', '),
            'report_date' => $filters['report_date'] ?: 'Todas las fechas',
            'search' => $filters['search'] ?: 'Sin búsqueda',
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

    private function canViewReports(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.reports');
    }
}
