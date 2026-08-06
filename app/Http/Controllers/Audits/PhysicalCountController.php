<?php

namespace App\Http\Controllers\Audits;

use App\Events\PhysicalCountChanged;
use App\Events\RealtimeActivityLogged;
use App\Exports\PhysicalCountExport;
use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\PhysicalCountRound;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Models\User;
use App\Services\PhysicalCountSnapshotService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountController extends Controller
{
    use AuthorizesBranchAccess;

    public function __construct(
        private PhysicalCountSnapshotService $snapshotService
    ) {}

    public function index(Request $request)
    {
        $branch = $this->resolveBranch($request->query('branch'));
        $user = $request->user();
        $perPage = TablePagination::resolvePerPage($request, 25);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');

        $physicalCountsQuery = PhysicalCount::with(['branch', 'creator', 'participants:id,name'])
            ->withMax('rounds as current_round_number', 'round_number')
            ->where('branch_id', $branch->id)
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($search, function ($query, $value) {
                FlexibleSearch::apply($query, $value, function ($subQuery, $phrase, $terms) {
                    FlexibleSearch::orWhereColumns($subQuery, [
                        'name',
                        'folio',
                        'status',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($subQuery, 'participants', [
                        'name',
                    ], $phrase, $terms);
                });
            })
            ->latest();

        if (! $this->canManageAudits($user)) {
            $physicalCountsQuery->whereHas('participants', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            });
        }

        return Inertia::render('Audits/PhysicalCounts/Index', [
            'branch' => $branch,
            'branches' => Branch::where('active', true)
                ->select('id', 'name', 'slug', 'color')
                ->orderBy('name')
                ->get(),
            'physicalCounts' => $physicalCountsQuery
                ->paginate($perPage)
                ->withQueryString(),
            'users' => $this->availableAuditUsers(),
            'canViewReports' => $this->canViewReports($request),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function reports(Request $request)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para ver reportes de auditoría.');

        $branch = $this->resolveBranch($request->query('branch'));

        $filters = [
            'branch' => $branch->slug,
            'physical_count_id' => $request->input('physical_count_id'),
            'user_id' => $request->input('user_id'),
            'category_id' => $request->input('category_id'),
            'year' => $request->input('year'),
            'month' => $request->input('month'),
            'day' => $request->input('day'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'holiday_start' => $request->input('holiday_start'),
            'holiday_end' => $request->input('holiday_end'),
            'status' => $request->input('status', ''),
            'search' => trim((string) $request->input('search', '')),
        ];

        $auditsQuery = PhysicalCount::with(['branch', 'creator', 'participants:id,name'])
            ->where('branch_id', $branch->id)
            ->when($filters['physical_count_id'], fn ($query, $id) => $query->where('id', $id))
            ->when($filters['year'], fn ($query, $year) => $query->whereYear('started_at', $year))
            ->when($filters['month'], fn ($query, $month) => $query->whereMonth('started_at', $month))
            ->when($filters['day'], fn ($query, $day) => $query->whereDay('started_at', $day))
            ->when($filters['start_date'], fn ($query, $date) => $query->whereDate('started_at', '>=', $date))
            ->when($filters['end_date'], fn ($query, $date) => $query->whereDate('started_at', '<=', $date))
            ->latest();

        $audits = $auditsQuery->get();
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
            'branchProduct.product.barcodes:id,product_id,code',
        ])
            ->whereIn('physical_count_id', $auditIds)
            ->when($filters['user_id'], fn ($query, $userId) => $query->where('user_id', $userId))
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

                    FlexibleSearch::orWhereHasColumns($subQuery, 'branchProduct.product.barcodes', [
                        'code',
                    ], $phrase, $terms);

                    FlexibleSearch::orWhereHasColumns($subQuery, 'user', [
                        'name',
                    ], $phrase, $terms);
                });
            })
            ->when($allowedBranchProductIds !== [], fn ($query) => $query->whereIn('branch_product_id', $allowedBranchProductIds))
            ->get();

        $activeBranchProductsQuery = BranchProduct::with([
            'product.category:id,name',
            'product.subcategory:id,name,category_id',
        ])
            ->where('branch_id', $branch->id)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->when($filters['category_id'], function ($query, $categoryId) {
                $query->whereHas('product', fn ($productQuery) => $productQuery->where('category_id', $categoryId));
            })
            ->when($filters['holiday_start'] && $filters['holiday_end'], function ($query) use ($filters) {
                $query->whereNotNull('season_start_date')
                    ->whereNotNull('season_end_date')
                    ->whereDate('season_start_date', '<=', $filters['holiday_end'])
                    ->whereDate('season_end_date', '>=', $filters['holiday_start']);
            });

        $activeBranchProducts = $activeBranchProductsQuery->get();

        $comparisonRows = $this->buildComparisonRows($audits, $entries, $snapshotRows);
        $pendingRows = $this->buildPendingRows($snapshotRows, $comparisonRows, $audits, $activeBranchProducts);
        $reportRows = collect([...$comparisonRows, ...$pendingRows]);

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

        $summary = [
            'audits' => $audits->count(),
            'records' => $entries->count(),
            'participants' => $entries->pluck('user_id')->filter()->unique()->count(),
            'counted_products' => collect($comparisonRows)->count(),
            'pending_products' => collect($pendingRows)->count(),
            'missing_products' => collect($comparisonRows)->where('status', 'missing')->count(),
            'surplus_products' => collect($comparisonRows)->where('status', 'surplus')->count(),
            'matched_products' => collect($comparisonRows)->where('status', 'matched')->count(),
        ];

        return Inertia::render('Inventory/Reports/PhysicalCountsReports', [
            'branch' => $branch,
            'branches' => Branch::where('active', true)
                ->select('id', 'name', 'slug', 'color')
                ->orderBy('name')
                ->get(),
            'filters' => $filters,
            'summary' => $summary,
            'audits' => $audits->map(fn ($audit) => [
                'id' => $audit->id,
                'name' => $audit->name,
                'folio' => $audit->folio,
                'status' => $audit->status,
                'started_at' => optional($audit->started_at)->toDateTimeString(),
            ])->values(),
            'users' => $this->availableAuditUsers(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'reportRows' => $reportRows->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch' => ['required', 'string', 'exists:branches,slug'],
            'name' => ['required', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $branch = $this->resolveBranch($data['branch']);

        $physicalCount = DB::transaction(function () use ($data, $branch) {
            $physicalCount = PhysicalCount::create([
                'folio' => 'AUD-PENDING-'.Str::uuid(),
                'branch_id' => $branch->id,
                'created_by' => Auth::id(),
                'name' => $data['name'],
                'status' => 'open',
                'started_at' => now(),
            ]);
            $physicalCount->update([
                'folio' => 'AUD-'.now()->format('Ymd').'-'.str_pad((string) $physicalCount->id, 4, '0', STR_PAD_LEFT),
            ]);

            $participantIds = collect($data['participant_ids'])
                ->push(Auth::id())
                ->filter()
                ->unique()
                ->values();

            $physicalCount->participants()->sync($participantIds);
            $physicalCount->rounds()->create([
                'round_number' => 1,
                'type' => 'original',
                'scope' => 'all',
                'opened_by' => Auth::id(),
                'started_at' => $physicalCount->started_at,
            ]);
            $this->snapshotService->ensureForAudit($physicalCount);

            return $physicalCount->fresh();
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'created'))->toOthers();
        event(RealtimeActivityLogged::message('creó', 'la auditoría', $physicalCount->folio, 'Auditorías', 'created'));

        return redirect()
            ->route('audits.physical-counts.index', ['branch' => $branch->slug])
            ->with('success', 'Conteo físico creado correctamente.');
    }

    public function update(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.participants');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $physicalCount->update([
            'name' => $data['name'],
        ]);

        $participantIds = collect($data['participant_ids'])
            ->filter()
            ->unique()
            ->values();

        $participantChanges = $physicalCount->participants()->sync($participantIds);

        broadcast(new PhysicalCountChanged($physicalCount->fresh(), 'participants_updated', [
            'attached_user_ids' => array_values($participantChanges['attached'] ?? []),
            'detached_user_ids' => array_values($participantChanges['detached'] ?? []),
        ]))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'la auditoría', $physicalCount->folio, 'Auditorías', 'updated'));

        return back()->with('success', 'Auditoría actualizada correctamente.');
    }

    public function destroy(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.delete');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        if ($physicalCount->status !== 'open') {
            return back()->withErrors([
                'status' => 'Solo auditorías abiertas pueden eliminarse.',
            ]);

        }

        $branchSlug = $physicalCount->branch?->slug;
        $folio = $physicalCount->folio;
        DB::transaction(function () use ($physicalCount) {
            $lockedAudit = PhysicalCount::query()->lockForUpdate()->findOrFail($physicalCount->id);

            if ($lockedAudit->status !== 'open') {
                throw ValidationException::withMessages([
                    'status' => 'La auditoria cambio de estado y ya no puede eliminarse.',
                ]);
            }

            $lockedAudit->delete();
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'deleted'));
        event(RealtimeActivityLogged::message('eliminó', 'la auditoría', $folio, 'Auditorías', 'deleted'));

        return redirect()
            ->route('audits.physical-counts.index', ['branch' => $branchSlug])
            ->with('success', 'Auditoría eliminada correctamente.');
    }

    public function show(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if ($this->canCaptureInStatus($physicalCount)) {
            $this->snapshotService->ensureForAudit($physicalCount);
        } else {
            $physicalCount->load('snapshot.items');
        }

        $physicalCount->load([
            'branch',
            'creator',
            'participants:id,name',
            'rounds.opener:id,name',
            'currentRound.opener:id,name',
        ]);

        return Inertia::render('Audits/PhysicalCounts/Show', [
            'physicalCount' => $physicalCount,
            'scannedProduct' => session('scannedProduct'),
            'canViewReports' => $this->canViewReports($request),
        ]);
    }

    public function showEntry(Request $request, PhysicalCountEntry $entry)
    {
        $entry->load('physicalCount.branch');
        $this->abortIfUserCannotAccessBranch($request, $entry->physicalCount->branch);

        $user = $request->user();
        abort_unless(
            ($user?->hasPermission('audits.physical-counts.count')
                && $this->isAssignedParticipant($user, $entry->physicalCount))
            || $user?->hasPermission('audits.physical-counts.delete'),
            403,
            'No tienes permisos para consultar este registro de auditoría.'
        );

        return response()->json(
            $entry->load(['branchProduct.product', 'productBatch', 'user', 'physicalCount'])
        );
    }

    public function storeEntry(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);
        $this->snapshotService->ensureForAudit($physicalCount);

        if (! $this->canCaptureInStatus($physicalCount)) {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden registrar conteos.',
            ]);
        }

        $data = $request->validate([
            'branch_product_id' => ['required', 'exists:branch_products,id'],
            'product_batch_id' => ['required', 'exists:product_batches,id'],
            'scanned_code' => ['nullable', 'string', 'max:255'],
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'damaged_quantity' => ['nullable', 'numeric', 'min:0'],
            'expired_quantity' => ['nullable', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $counted = (float) $data['counted_quantity'];
        $damaged = (float) ($data['damaged_quantity'] ?? 0);
        $expired = (float) ($data['expired_quantity'] ?? 0);

        if (($damaged + $expired) > $counted) {
            return back()->withErrors([
                'damaged_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
                'expired_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
            ]);
        }

        $branchProduct = BranchProduct::with('product')->findOrFail($data['branch_product_id']);
        $this->validateAuditQuantities($branchProduct, $counted, $damaged, $expired);

        if ($branchProduct->branch_id !== $physicalCount->branch_id) {
            return back()->withErrors([
                'branch_product_id' => 'El producto no pertenece a la sucursal de esta auditoría.',
            ]);
        }

        if (! $this->canRecaptureBranchProduct($physicalCount, $branchProduct)) {
            return back()->withErrors([
                'branch_product_id' => 'Esta auditoría está reactivada solo para productos con stock en cero.',
            ]);
        }

        $batchBelongsToProduct = ProductBatch::where('id', $data['product_batch_id'])
            ->where('branch_product_id', $branchProduct->id)
            ->exists();

        if (! $batchBelongsToProduct) {
            return back()->withErrors([
                'product_batch_id' => 'El lote seleccionado no pertenece al producto de esta auditoría.',
            ]);
        }

        $entry = DB::transaction(function () use ($physicalCount, $data, $branchProduct, $counted, $damaged, $expired) {
            $lockedAudit = $this->lockAuditForCapture($physicalCount->id);

            return PhysicalCountEntry::create([
                'physical_count_id' => $lockedAudit->id,
                'physical_count_round_id' => $this->currentRound($lockedAudit)->id,
                'branch_product_id' => $data['branch_product_id'],
                'product_batch_id' => $data['product_batch_id'],
                'product_id' => $branchProduct->product_id,
                'user_id' => Auth::id(),
                'scanned_code' => $data['scanned_code'] ?? null,
                'counted_quantity' => $counted,
                'damaged_quantity' => $damaged,
                'expired_quantity' => $expired,
                'expiration_date' => $data['expiration_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $entry->load('user:id,name');
        broadcast(new PhysicalCountChanged($physicalCount, 'entry_created', [
            'entry' => [
                'id' => $entry->id,
                'branch_product_id' => $entry->branch_product_id,
                'product_batch_id' => $entry->product_batch_id,
                'user' => $entry->user ? ['id' => $entry->user->id, 'name' => $entry->user->name] : null,
            ],
        ]))->toOthers();
        event(RealtimeActivityLogged::message('registró', 'una captura en la auditoría', $physicalCount->folio, 'Auditorías', 'entry_created'));

        return redirect()
            ->route('audits.physical-counts.show', $physicalCount)
            ->with('success', 'Conteo guardado correctamente.');
    }

    public function updateEntry(Request $request, PhysicalCountEntry $entry)
    {
        $entry->load('physicalCount', 'branchProduct.product');
        $this->abortIfCannotCapture($request, $entry->physicalCount);

        if (! $this->canCaptureInStatus($entry->physicalCount)) {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden editar conteos.',
            ]);
        }

        $data = $request->validate([
            'counted_quantity' => ['required', 'numeric', 'min:0'],
            'damaged_quantity' => ['required', 'numeric', 'min:0'],
            'expired_quantity' => ['required', 'numeric', 'min:0'],
            'expiration_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $counted = (float) $data['counted_quantity'];
        $damaged = (float) $data['damaged_quantity'];
        $expired = (float) $data['expired_quantity'];
        $this->validateAuditQuantities($entry->branchProduct, $counted, $damaged, $expired);

        if (($damaged + $expired) > $counted) {
            return back()->withErrors([
                'damaged_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
                'expired_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
            ]);
        }

        DB::transaction(function () use ($entry, $data) {
            $this->lockAuditForCapture($entry->physical_count_id);
            PhysicalCountEntry::query()->lockForUpdate()->findOrFail($entry->id)->update($data);
        });
        broadcast(new PhysicalCountChanged($entry->physicalCount, 'entry_updated', [
            'entry' => [
                'id' => $entry->id,
                'branch_product_id' => $entry->branch_product_id,
                'product_batch_id' => $entry->product_batch_id,
            ],
        ]))->toOthers();
        event(RealtimeActivityLogged::message('actualizó', 'una captura en la auditoría', $entry->physicalCount->folio, 'Auditorías', 'entry_updated'));

        return back()->with('success', 'Registro actualizado correctamente.');
    }

    public function destroyEntry(Request $request, PhysicalCountEntry $entry)
    {
        $entry->load('physicalCount');
        $this->abortUnless($request, 'audits.physical-counts.delete');
        $this->abortIfUserCannotAccessBranch($request, $entry->physicalCount->branch);

        if (! $this->canCaptureInStatus($entry->physicalCount)) {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden eliminar conteos.',
            ]);
        }

        $physicalCount = $entry->physicalCount;
        $deletedEntry = [
            'id' => $entry->id,
            'branch_product_id' => $entry->branch_product_id,
            'product_batch_id' => $entry->product_batch_id,
        ];
        DB::transaction(function () use ($entry) {
            $this->lockAuditForCapture($entry->physical_count_id);
            PhysicalCountEntry::query()->lockForUpdate()->findOrFail($entry->id)->delete();
        });

        $remainingEntries = $this->currentRoundEntriesQuery($physicalCount)
            ->where('product_batch_id', $deletedEntry['product_batch_id'])
            ->with('user:id,name')
            ->get();
        broadcast(new PhysicalCountChanged($physicalCount, 'entry_deleted', [
            'entry' => $deletedEntry,
            'batch_status' => [
                'is_counted' => $remainingEntries->isNotEmpty(),
                'count_records' => $remainingEntries->count(),
                'counted_by' => $remainingEntries->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
                    ->values()
                    ->all(),
            ],
        ]))->toOthers();
        event(RealtimeActivityLogged::message('eliminó', 'una captura en la auditoría', $physicalCount->folio, 'Auditorías', 'entry_deleted'));

        return back()->with('success', 'Registro eliminado correctamente.');
    }

    public function searchProducts(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if (! $this->canCaptureInStatus($physicalCount)) {
            return response()->json([]);
        }

        $search = trim($request->query('search', ''));

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $canViewStock = $this->canViewAuditStock($request);

        $products = BranchProduct::with('product')
            ->where('branch_id', $physicalCount->branch_id)
            ->when($physicalCount->recapture_scope === 'zero_stock', fn ($query) => $query->where('stock', '<=', 0))
            ->where(function ($query) use ($search) {
                $terms = FlexibleSearch::terms($search);

                FlexibleSearch::orWhereColumns($query, ['branch_products.barcode'], $search, $terms);
                FlexibleSearch::orWhereHasColumns($query, 'product', ['name'], $search, $terms);

                FlexibleSearch::orWhereExists($query, function ($subQuery) use ($search, $terms) {
                    $subQuery->select(DB::raw(1))
                        ->from('barcodes')
                        ->whereColumn('barcodes.product_id', 'branch_products.product_id')
                        ->where('barcodes.active', 1)
                        ->where(function ($barcodeQuery) use ($search, $terms) {
                            FlexibleSearch::orWhereColumns($barcodeQuery, ['barcodes.code'], $search, $terms);
                        });
                });
            })
            ->limit(10)
            ->get()
            ->map(function ($branchProduct) use ($search, $canViewStock) {
                $branchProduct->loadMissing('product.barcodes');
                $codes = collect([$branchProduct->barcode])
                    ->merge($branchProduct->product?->barcodes?->pluck('code') ?? [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique()
                    ->values();
                $matchedCode = $codes->first();

                if (! $matchedCode) {
                    $matchedCode = DB::table('barcodes')
                        ->where('product_id', $branchProduct->product_id)
                        ->where('code', $search)
                        ->value('code');
                }

                return [
                    'branch_product_id' => $branchProduct->id,
                    'product_id' => $branchProduct->product_id,
                    'name' => $branchProduct->product?->name ?? 'Sin producto',
                    'barcode' => $codes->first(),
                    'primary_code' => $codes->first(),
                    'related_codes' => $codes->slice(1)->values()->all(),
                    'matched_code' => $matchedCode,
                ] + ($canViewStock ? ['stock' => $branchProduct->stock] : []);
            })
            ->values();

        return response()->json($products);
    }

    public function scan(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if (! $this->canCaptureInStatus($physicalCount)) {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden escanear productos.',
            ]);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:255'],
            'branch_product_id' => ['nullable', 'exists:branch_products,id'],
        ]);

        $branchProduct = null;
        $code = trim($data['code'] ?? '');

        if (! empty($data['branch_product_id'])) {
            $branchProduct = BranchProduct::with('product')
                ->where('id', $data['branch_product_id'])
                ->where('branch_id', $physicalCount->branch_id)
                ->first();
        }

        if (! $branchProduct && $code !== '') {
            $branchProduct = BranchProduct::with('product')
                ->where('branch_id', $physicalCount->branch_id)
                ->where('barcode', $code)
                ->first();
        }

        if (! $branchProduct && $code !== '') {
            $barcode = DB::table('barcodes')
                ->where('code', $code)
                ->where('active', 1)
                ->first();

            if ($barcode) {
                $branchProduct = BranchProduct::with('product')
                    ->where('branch_id', $physicalCount->branch_id)
                    ->where('product_id', $barcode->product_id)
                    ->first();
            }
        }

        if (! $branchProduct && $code !== '') {
            $branchProduct = BranchProduct::with('product')
                ->where('branch_id', $physicalCount->branch_id)
                ->whereHas('product', fn ($query) => $query->where('name', 'LIKE', "%{$code}%"))
                ->first();
        }

        if (! $branchProduct) {
            return back()->withErrors([
                'code' => 'No se encontró un producto con ese código o nombre en la sucursal auditada.',
            ]);
        }

        if (! $this->canRecaptureBranchProduct($physicalCount, $branchProduct)) {
            return back()->withErrors([
                'code' => 'Esta auditoría está reactivada solo para productos con stock en cero.',
            ]);
        }

        return back()->with([
            'scannedProduct' => $this->scannedProductPayload($request, $physicalCount, $branchProduct, $code),
        ]);
    }

    public function storeBatch(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if (! $this->canCaptureInStatus($physicalCount)) {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden crear lotes.',
            ]);
        }

        $data = $request->validate([
            'branch_product_id' => ['required', 'exists:branch_products,id'],
            'scanned_code' => ['nullable', 'string', 'max:255'],
            'lot_number' => ['required', 'string', 'max:100'],
            'expiration_date' => ['required', 'date', 'after:today'],
            'supplier' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $branchProduct = BranchProduct::with('product')
            ->where('id', $data['branch_product_id'])
            ->where('branch_id', $physicalCount->branch_id)
            ->firstOrFail();

        if (! $this->canRecaptureBranchProduct($physicalCount, $branchProduct)) {
            return back()->withErrors([
                'branch_product_id' => 'Esta auditoría está reactivada solo para productos con stock en cero.',
            ]);
        }

        $lotNumber = $this->formatLotNumber($data['lot_number']);

        DB::transaction(function () use ($physicalCount, $branchProduct, $lotNumber, $data) {
            $this->lockAuditForCapture($physicalCount->id);
            ProductBatch::firstOrCreate(
                [
                    'branch_product_id' => $branchProduct->id,
                    'lot_number' => $lotNumber,
                ],
                [
                    'expiration_date' => $data['expiration_date'],
                    'initial_quantity' => 0,
                    'quantity' => 0,
                    'supplier' => $data['supplier'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'received_at' => now()->toDateString(),
                    'status' => ProductBatch::STATUS_INACTIVE,
                    'has_real_lot' => true,
                    'entry_type' => 'PURCHASE_BATCH',
                ]
            );
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'batch_created'));
        event(RealtimeActivityLogged::message('creó', 'un lote en la auditoría', $physicalCount->folio, 'Auditorías', 'batch_created'));

        return back()->with([
            'success' => 'Lote creado correctamente para la auditoría.',
            'scannedProduct' => $this->scannedProductPayload(
                $request,
                $physicalCount,
                $branchProduct,
                $data['scanned_code'] ?? $branchProduct->barcode
            ),
        ]);
    }

    public function close(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.close');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        if ($physicalCount->status !== 'open') {
            return back()->withErrors([
                'status' => 'Solo auditorías abiertas o aplicadas pueden cerrarse.',
            ]);
        }

        DB::transaction(function () use ($physicalCount) {
            $lockedAudit = $this->lockAuditForCapture($physicalCount->id);
            $closedAt = now();
            $lockedAudit->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
            ]);
            $this->currentRound($lockedAudit)->update(['closed_at' => $closedAt]);
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'closed'));
        event(RealtimeActivityLogged::message('cerró', 'la auditoría', $physicalCount->folio, 'Auditorías', 'closed'));

        return back()->with('success', 'Auditoría cerrada correctamente.');
    }

    public function reopen(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.reopen');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        if ($physicalCount->status !== 'closed') {
            return back()->withErrors([
                'status' => 'Solo auditorías cerradas pueden reabrirse.',
            ]);
        }

        $data = $request->validate([
            'recapture_scope' => ['nullable', 'in:all,zero_stock'],
        ]);
        $recaptureScope = $data['recapture_scope'] ?? 'all';
        DB::transaction(function () use ($physicalCount, $recaptureScope): void {
            $audit = PhysicalCount::whereKey($physicalCount->id)->lockForUpdate()->firstOrFail();

            if ($audit->status !== 'closed') {
                throw ValidationException::withMessages([
                    'status' => 'La auditoria cambio de estado y ya no puede reabrirse.',
                ]);
            }

            $startedAt = now();
            $nextRound = ((int) $audit->rounds()->max('round_number')) + 1;

            $audit->rounds()->create([
                'round_number' => $nextRound,
                'type' => 'reopening',
                'scope' => $recaptureScope,
                'opened_by' => Auth::id(),
                'started_at' => $startedAt,
            ]);

            $audit->update([
                'status' => 'open',
                'closed_at' => null,
                'recapture_scope' => $recaptureScope,
                'recapture_started_at' => $startedAt,
            ]);
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'reopened'));
        event(RealtimeActivityLogged::message('reabrió', 'la auditoría', $physicalCount->folio, 'Auditorías', 'reopened'));

        return back()->with('success', 'Auditoría reabierta correctamente.');
    }

    public function finalize(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.finalize');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        if ($physicalCount->status !== 'closed') {
            return back()->withErrors([
                'status' => 'Solo una auditoría con su ronda cerrada puede finalizarse.',
            ]);
        }

        DB::transaction(function () use ($physicalCount) {
            $lockedAudit = PhysicalCount::query()->lockForUpdate()->findOrFail($physicalCount->id);

            if ($lockedAudit->status !== 'closed') {
                throw ValidationException::withMessages([
                    'status' => 'La auditoria cambio de estado y ya no puede finalizarse.',
                ]);
            }

            $lockedAudit->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => Auth::id(),
            ]);
        });

        broadcast(new PhysicalCountChanged($physicalCount, 'finalized'));
        event(RealtimeActivityLogged::message('finalizó', 'la auditoría', $physicalCount->folio, 'Auditorías', 'finalized'));

        return back()->with('success', 'Auditoría finalizada. Ya puede aplicar sus ajustes.');
    }

    public function applyAdjustments(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortUnless($request, 'audits.physical-counts.apply');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        if ($physicalCount->status !== 'finalized') {
            return back()->withErrors([
                'status' => 'Solo se pueden aplicar ajustes de una auditoría finalizada o aplicada.',
            ]);
        }

        DB::transaction(function () use ($physicalCount) {
            $physicalCount = PhysicalCount::query()
                ->with('snapshot.items')
                ->lockForUpdate()
                ->findOrFail($physicalCount->id);

            if ($physicalCount->status !== 'finalized') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden aplicar ajustes de una auditoria finalizada.',
                ]);
            }

            $snapshotBatchStocks = $physicalCount->snapshot?->items
                ?->whereNotNull('product_batch_id')
                ->keyBy('product_batch_id');

            $comparison = $this->finalEntries($physicalCount)
                ->whereNotNull('product_batch_id')
                ->groupBy(fn ($entry) => $entry->branch_product_id.':'.$entry->product_batch_id)
                ->map(function ($entries) {
                    $first = $entries->first();

                    return (object) [
                        'branch_product_id' => $first->branch_product_id,
                        'product_batch_id' => $first->product_batch_id,
                        'counted_stock' => $entries->sum('counted_quantity'),
                        'damaged_stock' => $entries->sum('damaged_quantity'),
                        'expired_stock' => $entries->sum('expired_quantity'),
                    ];
                });

            foreach ($comparison as $item) {
                $batch = ProductBatch::whereKey($item->product_batch_id)
                    ->lockForUpdate()
                    ->first();

                if (! $batch) {
                    continue;
                }

                $branchProduct = BranchProduct::with('product')->whereKey($batch->branch_product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $branchProduct) {
                    continue;
                }

                $snapshotBatchQuantity = (float) ($snapshotBatchStocks?->get($batch->id)?->batch_stock ?? 0);
                $currentBatchQuantity = (float) $batch->quantity;
                $previousStock = (float) $branchProduct->stock;
                $countedStock = (float) $item->counted_stock;
                $damagedStock = (float) $item->damaged_stock;
                $expiredStock = (float) $item->expired_stock;
                $countedBatchQuantity = max(0, $countedStock - $damagedStock - $expiredStock);
                $difference = $countedBatchQuantity - $snapshotBatchQuantity;
                $newBatchQuantity = $currentBatchQuantity + $difference;

                if ($newBatchQuantity < 0) {
                    throw ValidationException::withMessages([
                        'stock' => "El lote {$batch->lot_number} cambio despues del conteo y la diferencia dejaria stock negativo.",
                    ]);
                }

                if ($difference === 0.0) {
                    continue;
                }

                $batch->update([
                    'quantity' => $newBatchQuantity,
                    'status' => $newBatchQuantity > 0
                        ? ProductBatch::STATUS_ACTIVE
                        : $batch->status,
                ]);

                $newStock = $this->syncBranchProductStockFromBatches($branchProduct);

                $movement = StockMovement::create([
                    'branch_product_id' => $branchProduct->id,
                    'type' => StockMovement::TYPE_ADJUSTMENT,
                    'reason' => StockMovement::REASON_INVENTORY_DIFFERENCE,
                    'quantity' => abs($difference),
                    'unit_cost' => $branchProduct->product?->cost_per_piece ?? $branchProduct->product?->cost ?? 0,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'user_id' => Auth::id(),
                    'notes' => sprintf(
                        'Ajuste aplicado desde auditoría %s | Contado: %s | Dañado: %s | Caducado: %s',
                        $physicalCount->folio,
                        $countedStock,
                        $damagedStock,
                        $expiredStock
                    ),
                ]);

                StockMovementBatch::create([
                    'stock_movement_id' => $movement->id,
                    'product_batch_id' => $batch->id,
                    'quantity' => abs($difference),
                    'previous_batch_quantity' => $currentBatchQuantity,
                    'new_batch_quantity' => $newBatchQuantity,
                    'allocation_method' => StockMovementBatch::ALLOCATION_MANUAL,
                ]);
            }

            $physicalCount->update([
                'status' => 'applied',
                'last_applied_at' => now(),
            ]);
            $this->currentRound($physicalCount)->update(['applied_at' => now()]);
        });
        broadcast(new PhysicalCountChanged($physicalCount, 'applied'));
        event(RealtimeActivityLogged::message('aplicó ajustes de', 'la auditoría', $physicalCount->folio, 'Auditorías', 'applied'));

        return redirect()
            ->route('audits.physical-counts.show', $physicalCount)
            ->with('success', 'Ajustes aplicados correctamente al inventario.');
    }

    private function syncBranchProductStockFromBatches(BranchProduct $branchProduct): float
    {
        $stock = (float) ProductBatch::where('branch_product_id', $branchProduct->id)
            ->whereIn('status', [
                ProductBatch::STATUS_ACTIVE,
                ProductBatch::STATUS_SEASONAL,
            ])
            ->where('quantity', '>', 0)
            ->sum('quantity');

        $branchProduct->update([
            'stock' => $stock,
        ]);

        return $stock;
    }

    public function exportPdf(Request $request, PhysicalCount $physicalCount)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoría.');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        $physicalCount->load(['branch', 'creator', 'snapshot.items']);
        $entriesQuery = $this->currentRoundEntriesQuery($physicalCount);
        $entries = $entriesQuery->with(['branchProduct.product', 'user', 'productBatch'])->get();
        $comparison = collect($this->buildComparisonRows(
            collect([$physicalCount]),
            $entries,
            $this->snapshotService->buildProductRows(collect([$physicalCount]))
        ));

        $summary = [
            'total_entries' => $entries->count(),
            'total_counted' => $entries->sum('counted_quantity'),
            'total_damaged' => $entries->sum('damaged_quantity'),
            'total_expired' => $entries->sum('expired_quantity'),
            'participants' => $entries->pluck('user_id')->filter()->unique()->count(),
            'audited_products' => $comparison->count(),
        ];

        $pdf = Pdf::loadView('pdf.physical-count', [
            'physicalCount' => $physicalCount,
            'summary' => $summary,
            'comparison' => $comparison,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('conteo-fisico-'.$physicalCount->id.'.pdf');
    }

    public function exportExcel(Request $request, PhysicalCount $physicalCount)
    {
        abort_unless($this->canViewReports($request), 403, 'No tienes permisos para exportar reportes de auditoría.');
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        return Excel::download(
            new PhysicalCountExport($physicalCount),
            'conteo-fisico-'.$physicalCount->id.'.xlsx'
        );
    }

    private function buildComparisonRows(Collection $audits, Collection $entries, ?Collection $snapshotRows = null): array
    {
        $auditsById = $audits->keyBy('id');
        $snapshotByKey = ($snapshotRows ?? collect())
            ->keyBy(fn ($row) => $row['physical_count_id'].':'.$row['branch_product_id']);

        return $entries
            ->groupBy(fn ($entry) => $entry->physical_count_id.':'.$entry->branch_product_id)
            ->map(function ($group, $groupKey) use ($auditsById, $snapshotByKey) {
                $first = $group->first();
                $audit = $auditsById->get($first->physical_count_id);
                $snapshot = $snapshotByKey->get($groupKey);
                $systemStock = (float) ($snapshot['system_stock'] ?? ($first->branchProduct?->stock ?? 0));
                $countedStock = (float) $group->sum('counted_quantity');
                $damagedStock = (float) $group->sum('damaged_quantity');
                $expiredStock = (float) $group->sum('expired_quantity');
                $sellableStock = max(0, $countedStock - $damagedStock - $expiredStock);
                $difference = $sellableStock - $systemStock;

                return [
                    'id' => $first->physical_count_id.'-'.$first->branch_product_id,
                    'row_type' => 'counted',
                    'status' => $difference < 0 ? 'missing' : ($difference > 0 ? 'surplus' : 'matched'),
                    'physical_count_id' => $first->physical_count_id,
                    'audit_name' => $audit?->name ?? 'Sin auditoría',
                    'folio' => $audit?->folio ?? 'Sin folio',
                    'audit_date' => optional($audit?->started_at)->toDateString(),
                    'branch_product_id' => $first->branch_product_id,
                    'product_name' => $snapshot['product_name'] ?? $first->branchProduct?->product?->name ?? 'Sin producto',
                    'category_name' => $snapshot['category_name'] ?? $first->branchProduct?->product?->category?->name ?? 'Sin categoría',
                    'subcategory_name' => $snapshot['subcategory_name'] ?? $first->branchProduct?->product?->subcategory?->name ?? 'Sin subcategoría',
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
    ): array {
        if ($snapshotRows->isNotEmpty()) {
            $countedKeys = collect($comparisonRows)
                ->map(fn ($row) => $row['physical_count_id'].':'.$row['branch_product_id'])
                ->unique();

            return $snapshotRows
                ->reject(fn ($row) => $countedKeys->contains($row['physical_count_id'].':'.$row['branch_product_id']))
                ->map(fn ($row) => [
                    'id' => 'pending-'.$row['physical_count_id'].'-'.$row['branch_product_id'],
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'physical_count_id' => $row['physical_count_id'],
                    'audit_name' => $row['audit_name'],
                    'folio' => $row['folio'],
                    'audit_date' => $row['audit_date'],
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
                    'id' => 'pending-'.$branchProduct->id,
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'physical_count_id' => null,
                    'audit_name' => $firstAudit?->name ?? 'Sin auditoría filtrada',
                    'folio' => $firstAudit?->folio ?? 'Sin folio',
                    'audit_date' => optional($firstAudit?->started_at)->toDateString(),
                    'branch_product_id' => $branchProduct->id,
                    'product_name' => $branchProduct->product?->name ?? 'Sin producto',
                    'category_name' => $branchProduct->product?->category?->name ?? 'Sin categoría',
                    'subcategory_name' => $branchProduct->product?->subcategory?->name ?? 'Sin subcategoría',
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

    private function validateAuditQuantities(
        BranchProduct $branchProduct,
        float $counted,
        float $damaged,
        float $expired
    ): void {
        $product = $branchProduct->product;

        if ($product?->inventory_quantity_mode === 'legacy_presentation') {
            throw ValidationException::withMessages([
                'counted_quantity' => 'Este producto conserva existencias históricas en cajas y debe conciliarse antes de auditarse.',
            ]);
        }

        $values = compact('counted', 'damaged', 'expired');
        $isKilogram = ($product?->inventory_unit ?? $product?->unit ?? 'pza') === 'kg';

        foreach ($values as $field => $value) {
            if ($isKilogram && ($value > 999.999 || abs($value - round($value, 3)) > 0.0000001)) {
                throw ValidationException::withMessages([
                    "{$field}_quantity" => 'Los kilogramos permiten hasta tres decimales y un máximo de 999.999.',
                ]);
            }

            if (! $isKilogram && abs($value - round($value)) > 0.0000001) {
                throw ValidationException::withMessages([
                    "{$field}_quantity" => 'Las piezas deben registrarse con números enteros.',
                ]);
            }
        }
    }

    private function scannedProductPayload(
        Request $request,
        PhysicalCount $physicalCount,
        BranchProduct $branchProduct,
        ?string $code = null
    ): array {
        $canViewStock = $this->canViewAuditStock($request);
        $branchProduct->loadMissing('product.barcodes');
        $productCodes = collect([$branchProduct->barcode])
            ->merge($branchProduct->product?->barcodes?->pluck('code') ?? [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique()
            ->values();
        $primaryCode = $productCodes->first();
        $physicalCount->loadMissing('snapshot.items');
        $snapshotRow = $this->snapshotService
            ->buildProductRows(collect([$physicalCount]))
            ->firstWhere('branch_product_id', $branchProduct->id);

        if ($snapshotRow) {
            $snapshotBatches = collect($snapshotRow['snapshot_batches'] ?? [])
                ->map(function ($batch) use ($canViewStock) {
                    $payload = [
                        'id' => $batch['id'],
                        'lot_number' => $batch['lot_number'],
                        'expiration_date' => $batch['expiration_date'],
                    ];

                    if ($canViewStock) {
                        $payload['quantity'] = $batch['quantity'];
                    }

                    return $payload;
                })
                ->values();

            $snapshotBatches = $snapshotBatches
                ->merge($this->auditBatchesPayload($branchProduct, $canViewStock))
                ->unique('id')
                ->values();

            $payload = [
                'branch_product_id' => $branchProduct->id,
                'product_id' => $branchProduct->product_id,
                'name' => $snapshotRow['product_name'],
                'inventory_unit' => $branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit ?? 'pza',
                'inventory_quantity_mode' => $branchProduct->product?->inventory_quantity_mode ?? 'base',
                'barcode' => $primaryCode ?: ($snapshotRow['scanned_code'] ?: $code),
                'primary_code' => $primaryCode,
                'related_codes' => $productCodes->slice(1)->values()->all(),
                'has_box_presentation' => (bool) $branchProduct->product?->has_box_presentation,
                'pieces_per_box' => $branchProduct->product?->pieces_per_box,
                'scanned_code' => $code ?: ($snapshotRow['scanned_code'] ?: 'Sin código escaneado'),
                'batches' => $snapshotBatches,
            ];

            if ($canViewStock) {
                $payload['stock'] = (float) $snapshotRow['system_stock'];
            }

            $payload['batches'] = $this->withBatchCountingStatus(
                $physicalCount,
                $branchProduct,
                collect($payload['batches'])
            );

            return $payload;
        }

        $batches = $this->auditBatchesPayload($branchProduct, $canViewStock);

        $payload = [
            'branch_product_id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'name' => $branchProduct->product->name ?? 'Sin producto',
            'inventory_unit' => $branchProduct->product?->inventory_unit ?? $branchProduct->product?->unit ?? 'pza',
            'inventory_quantity_mode' => $branchProduct->product?->inventory_quantity_mode ?? 'base',
            'barcode' => $primaryCode ?: ($branchProduct->barcode ?? $code),
            'primary_code' => $primaryCode,
            'related_codes' => $productCodes->slice(1)->values()->all(),
            'has_box_presentation' => (bool) $branchProduct->product?->has_box_presentation,
            'pieces_per_box' => $branchProduct->product?->pieces_per_box,
            'scanned_code' => $code ?: ($branchProduct->barcode ?? 'Sin código escaneado'),
            'batches' => $batches,
        ];

        if ($canViewStock) {
            $payload['stock'] = $branchProduct->stock;
        }

        $payload['batches'] = $this->withBatchCountingStatus(
            $physicalCount,
            $branchProduct,
            collect($payload['batches'])
        );

        return $payload;
    }

    private function auditBatchesPayload(BranchProduct $branchProduct, bool $canViewStock): Collection
    {
        return ProductBatch::where('branch_product_id', $branchProduct->id)
            ->where(function ($query) {
                $query->where('quantity', '>', 0)
                    ->orWhere(function ($pendingQuery) {
                        $pendingQuery
                            ->where('quantity', 0)
                            ->where('status', ProductBatch::STATUS_INACTIVE);
                    });
            })
            ->orderByRaw('expiration_date IS NULL')
            ->orderBy('expiration_date')
            ->orderBy('id')
            ->get(['id', 'lot_number', 'quantity', 'expiration_date', 'status'])
            ->map(function ($batch) use ($canViewStock) {
                $payload = [
                    'id' => $batch->id,
                    'lot_number' => $batch->lot_number,
                    'expiration_date' => optional($batch->expiration_date)->toDateString(),
                    'status' => $batch->status,
                ];

                if ($canViewStock) {
                    $payload['quantity'] = $batch->quantity;
                }

                return $payload;
            })
            ->values();
    }

    private function withBatchCountingStatus(
        PhysicalCount $physicalCount,
        BranchProduct $branchProduct,
        Collection $batches
    ): Collection {
        $countedByBatch = $this->currentRoundEntriesQuery($physicalCount)
            ->where('branch_product_id', $branchProduct->id)
            ->with('user:id,name')
            ->get()
            ->groupBy('product_batch_id');

        return $batches->map(function ($batch) use ($countedByBatch) {
            $batch = is_array($batch) ? $batch : $batch->toArray();
            $entries = $countedByBatch->get($batch['id'], collect());
            $users = $entries
                ->pluck('user')
                ->filter()
                ->unique('id')
                ->map(fn ($user) => ['id' => $user->id, 'name' => $user->name])
                ->values();

            return [
                ...$batch,
                'is_counted' => $entries->isNotEmpty(),
                'counted_by' => $users,
                'count_records' => $entries->count(),
            ];
        })->values();
    }

    private function hydrateAuditSnapshots(Collection $audits): void
    {
        $audits->load('snapshot.items');

        $audits
            ->filter(fn ($audit) => $audit->status === 'open' && $audit->snapshot === null)
            ->each(fn ($audit) => $this->snapshotService->ensureForAudit($audit));

        $audits->load('snapshot.items');
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

    private function resolveBranch(?string $branchSlug): Branch
    {
        return $this->resolveAccessibleBranch(request(), $branchSlug);
    }

    private function canManageAudits(?User $user): bool
    {
        return (bool) ($user?->hasPermission('audits.physical-counts.create')
            || $user?->hasPermission('audits.physical-counts.close')
              || $user?->hasPermission('audits.physical-counts.reopen')
              || $user?->hasPermission('audits.physical-counts.finalize')
              || $user?->hasPermission('audits.physical-counts.participants')
              || $user?->hasPermission('audits.physical-counts.apply'));
    }

    private function canViewReports(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('reports.audits.view');
    }

    private function canViewAuditStock(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.view-stock');
    }

    private function canCaptureInStatus(PhysicalCount $physicalCount): bool
    {
        return $physicalCount->status === 'open';
    }

    private function lockAuditForCapture(int $physicalCountId): PhysicalCount
    {
        $physicalCount = PhysicalCount::query()
            ->lockForUpdate()
            ->findOrFail($physicalCountId);

        if (! $this->canCaptureInStatus($physicalCount)) {
            throw ValidationException::withMessages([
                'status' => 'La auditoria cambio de estado y ya no acepta capturas.',
            ]);
        }

        return $physicalCount;
    }

    private function canRecaptureBranchProduct(PhysicalCount $physicalCount, BranchProduct $branchProduct): bool
    {
        return $physicalCount->recapture_scope !== 'zero_stock'
            || (float) $branchProduct->stock <= 0;
    }

    private function currentRoundEntriesQuery(PhysicalCount $physicalCount)
    {
        return $physicalCount->entries()
            ->where('physical_count_round_id', $this->currentRound($physicalCount)->id);
    }

    private function finalEntries(PhysicalCount $physicalCount)
    {
        return $physicalCount->entries()
            ->with('round:id,round_number')
            ->get()
            ->groupBy('branch_product_id')
            ->flatMap(function ($entries) {
                $latestRound = $entries->max(fn ($entry) => (int) ($entry->round?->round_number ?? 1));

                return $entries->filter(
                    fn ($entry) => (int) ($entry->round?->round_number ?? 1) === $latestRound
                );
            })
            ->values();
    }

    private function currentRound(PhysicalCount $physicalCount): PhysicalCountRound
    {
        return $physicalCount->rounds()
            ->latest('round_number')
            ->firstOrFail();
    }

    private function isAssignedParticipant(?User $user, PhysicalCount $physicalCount): bool
    {
        if (! $user) {
            return false;
        }

        return $physicalCount->participants()
            ->where('users.id', $user->id)
            ->exists();
    }

    private function abortIfCannotCapture(Request $request, PhysicalCount $physicalCount): void
    {
        $user = $request->user();
        $this->abortIfUserCannotAccessBranch($request, $physicalCount->branch);

        abort_unless(
            $user?->hasPermission('audits.physical-counts.count') && $this->isAssignedParticipant($user, $physicalCount),
            403,
            'No tienes permisos para capturar en esta auditoría.'
        );
    }

    private function abortUnless(Request $request, string $permission): void
    {
        abort_unless(
            $request->user()?->hasPermission($permission),
            403,
            'No tienes permisos para administrar esta auditoría.'
        );
    }

    private function formatLotNumber(string $value): string
    {
        return preg_replace('/-+/', '-', preg_replace('/\s+/', '-', trim($value)));
    }
}
