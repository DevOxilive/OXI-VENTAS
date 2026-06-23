<?php

namespace App\Http\Controllers\Audits;

use App\Events\PhysicalCountChanged;
use App\Exports\PhysicalCountExport;
use App\Exports\PhysicalCountReportExport;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\PhysicalCount;
use App\Models\PhysicalCountEntry;
use App\Models\ProductAlternativeCode;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PhysicalCountController extends Controller
{
    public function index(Request $request)
    {
        $branch = $this->resolveBranch($request->query('branch'));
        $user = $request->user();

        $physicalCountsQuery = PhysicalCount::with(['branch', 'creator', 'participants:id,name'])
            ->where('branch_id', $branch->id)
            ->latest();

        if (!$this->canManageAudits($user)) {
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
            'physicalCounts' => $physicalCountsQuery->get(),
            'users' => $this->availableAuditUsers(),
            'canViewReports' => $this->canViewReports($request),
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
        $allowedBranchProductIds = $activeBranchProducts->pluck('id')->all();

        $comparisonRows = $this->buildComparisonRows($audits, $entries, $allowedBranchProductIds);
        $pendingRows = $this->buildPendingRows($activeBranchProducts, $comparisonRows, $audits);
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

        return Inertia::render('Audits/PhysicalCounts/Reports', [
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
            'branch_id' => ['required', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $branch = Branch::findOrFail($data['branch_id']);
        $nextId = (PhysicalCount::max('id') ?? 0) + 1;
        $folio = 'AUD-' . now()->format('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $physicalCount = PhysicalCount::create([
            'folio' => $folio,
            'branch_id' => $branch->id,
            'created_by' => Auth::id(),
            'name' => $data['name'],
            'status' => 'open',
            'started_at' => now(),
        ]);

        $participantIds = collect($data['participant_ids'])
            ->push(Auth::id())
            ->filter()
            ->unique()
            ->values();

        $physicalCount->participants()->sync($participantIds);

        broadcast(new PhysicalCountChanged($physicalCount, 'created'))->toOthers();

        return redirect()
            ->route('audits.physical-counts.index', ['branch' => $branch->slug])
            ->with('success', 'Conteo físico creado correctamente.');
    }

    public function update(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['exists:users,id'],
        ]);

        $physicalCount->update([
            'name' => $data['name'],
        ]);

        $participantIds = collect($data['participant_ids'])
            ->push($physicalCount->created_by)
            ->filter()
            ->unique()
            ->values();

        $physicalCount->participants()->sync($participantIds);

        broadcast(new PhysicalCountChanged($physicalCount, 'updated'))->toOthers();

        return back()->with('success', 'Auditoría actualizada correctamente.');
    }

    public function destroy(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
            return back()->withErrors([
                'status' => 'Solo auditorías abiertas pueden eliminarse.',
            ]);
        }

        $branchSlug = $physicalCount->branch?->slug;
        $physicalCount->delete();

        broadcast(new PhysicalCountChanged($physicalCount, 'deleted'))->toOthers();

        return redirect()
            ->route('audits.physical-counts.index', ['branch' => $branchSlug])
            ->with('success', 'Auditoría eliminada correctamente.');
    }

    public function show(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        $physicalCount->load(['branch', 'creator', 'participants:id,name']);

        return Inertia::render('Audits/PhysicalCounts/Show', [
            'physicalCount' => $physicalCount,
            'scannedProduct' => session('scannedProduct'),
            'canViewReports' => $this->canViewReports($request),
        ]);
    }

    public function showEntry(PhysicalCountEntry $entry)
    {
        return response()->json(
            $entry->load(['branchProduct.product', 'productBatch', 'user', 'physicalCount'])
        );
    }

    public function storeEntry(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
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

        $branchProduct = BranchProduct::findOrFail($data['branch_product_id']);

        if ($branchProduct->branch_id !== $physicalCount->branch_id) {
            return back()->withErrors([
                'branch_product_id' => 'El producto no pertenece a la sucursal de esta auditoría.',
            ]);
        }

        $batchBelongsToProduct = ProductBatch::where('id', $data['product_batch_id'])
            ->where('branch_product_id', $branchProduct->id)
            ->exists();

        if (!$batchBelongsToProduct) {
            return back()->withErrors([
                'product_batch_id' => 'El lote seleccionado no pertenece al producto de esta auditoría.',
            ]);
        }

        PhysicalCountEntry::create([
            'physical_count_id' => $physicalCount->id,
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

        broadcast(new PhysicalCountChanged($physicalCount, 'entry_created'))->toOthers();

        return redirect()
            ->route('audits.physical-counts.show', $physicalCount)
            ->with([
                'success' => 'Conteo guardado correctamente.',
                'scannedProduct' => $this->scannedProductPayload(
                    $request,
                    $physicalCount,
                    $branchProduct,
                    $data['scanned_code'] ?? $branchProduct->barcode
                ),
            ]);
    }

    public function updateEntry(Request $request, PhysicalCountEntry $entry)
    {
        $entry->load('physicalCount');
        $this->abortIfCannotManageAudit($request, $entry->physicalCount);

        if ($entry->physicalCount->status !== 'open') {
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

        if (($damaged + $expired) > $counted) {
            return back()->withErrors([
                'damaged_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
                'expired_quantity' => 'La suma de dañados y caducados no puede ser mayor a la cantidad contada.',
            ]);
        }

        $entry->update($data);
        broadcast(new PhysicalCountChanged($entry->physicalCount, 'entry_updated'))->toOthers();

        return back()->with('success', 'Registro actualizado correctamente.');
    }

    public function destroyEntry(Request $request, PhysicalCountEntry $entry)
    {
        $entry->load('physicalCount');
        $this->abortIfCannotManageAudit($request, $entry->physicalCount);

        if ($entry->physicalCount->status !== 'open') {
            return back()->withErrors([
                'status' => 'Esta auditoría no está abierta. No se pueden eliminar conteos.',
            ]);
        }

        $physicalCount = $entry->physicalCount;
        $entry->delete();

        broadcast(new PhysicalCountChanged($physicalCount, 'entry_deleted'))->toOthers();

        return back()->with('success', 'Registro eliminado correctamente.');
    }

    public function searchProducts(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
            return response()->json([]);
        }

        $search = trim($request->query('search', ''));

        if (strlen($search) < 2) {
            return response()->json([]);
        }

        $canViewStock = $this->canViewAuditStock($request);

        $products = BranchProduct::with('product')
            ->where('branch_id', $physicalCount->branch_id)
            ->where(function ($query) use ($search) {
                $query
                    ->where('branch_products.barcode', 'LIKE', "%{$search}%")
                    ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'LIKE', "%{$search}%"))
                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->select(DB::raw(1))
                            ->from('barcodes')
                            ->whereColumn('barcodes.product_id', 'branch_products.product_id')
                            ->where('barcodes.active', 1)
                            ->where('barcodes.code', 'LIKE', "%{$search}%");
                    })
                    ->orWhereExists(function ($subQuery) use ($search) {
                        $subQuery->select(DB::raw(1))
                            ->from('product_alternative_codes')
                            ->whereColumn('product_alternative_codes.product_id', 'branch_products.product_id')
                            ->where('product_alternative_codes.code', 'LIKE', "%{$search}%");
                    });
            })
            ->limit(10)
            ->get()
            ->map(function ($branchProduct) use ($search, $canViewStock) {
                $matchedCode = $branchProduct->barcode;

                if (!$matchedCode) {
                    $matchedCode = DB::table('barcodes')
                        ->where('product_id', $branchProduct->product_id)
                        ->where('code', $search)
                        ->value('code');
                }

                if (!$matchedCode) {
                    $matchedCode = DB::table('product_alternative_codes')
                        ->where('product_id', $branchProduct->product_id)
                        ->where('code', $search)
                        ->value('code');
                }

                return [
                    'branch_product_id' => $branchProduct->id,
                    'product_id' => $branchProduct->product_id,
                    'name' => $branchProduct->product?->name ?? 'Sin producto',
                    'barcode' => $branchProduct->barcode,
                    'matched_code' => $matchedCode,
                ] + ($canViewStock ? ['stock' => $branchProduct->stock] : []);
            })
            ->values();

        return response()->json($products);
    }

    public function scan(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
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

        if (!empty($data['branch_product_id'])) {
            $branchProduct = BranchProduct::with('product')
                ->where('id', $data['branch_product_id'])
                ->where('branch_id', $physicalCount->branch_id)
                ->first();
        }

        if (!$branchProduct && $code !== '') {
            $branchProduct = BranchProduct::with('product')
                ->where('branch_id', $physicalCount->branch_id)
                ->where('barcode', $code)
                ->first();
        }

        if (!$branchProduct && $code !== '') {
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

        if (!$branchProduct && $code !== '') {
            $alternativeCode = ProductAlternativeCode::where('code', $code)->first();

            if ($alternativeCode) {
                $branchProduct = BranchProduct::with('product')
                    ->where('branch_id', $physicalCount->branch_id)
                    ->where('product_id', $alternativeCode->product_id)
                    ->first();
            }
        }

        if (!$branchProduct && $code !== '') {
            $branchProduct = BranchProduct::with('product')
                ->where('branch_id', $physicalCount->branch_id)
                ->whereHas('product', fn ($query) => $query->where('name', 'LIKE', "%{$code}%"))
                ->first();
        }

        if (!$branchProduct) {
            return back()->withErrors([
                'code' => 'No se encontró un producto con ese código o nombre en la sucursal auditada.',
            ]);
        }

        return back()->with([
            'scannedProduct' => $this->scannedProductPayload($request, $physicalCount, $branchProduct, $code),
        ]);
    }

    public function storeBatch(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotCapture($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
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

        $lotNumber = $this->formatLotNumber($data['lot_number']);

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
                'received_at' => now()->toDateString(),
                'status' => ProductBatch::STATUS_ACTIVE,
            ]
        );

        broadcast(new PhysicalCountChanged($physicalCount, 'batch_created'))->toOthers();

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
        $this->abortIfCannotManageAudit($request, $physicalCount);

        if ($physicalCount->status !== 'open') {
            return back()->withErrors([
                'status' => 'Solo auditorías abiertas pueden cerrarse.',
            ]);
        }

        $physicalCount->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        broadcast(new PhysicalCountChanged($physicalCount, 'closed'))->toOthers();

        return back()->with('success', 'Auditoría cerrada correctamente.');
    }

    public function reopen(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        if ($physicalCount->status !== 'closed') {
            return back()->withErrors([
                'status' => 'Solo auditorías cerradas pueden reabrirse.',
            ]);
        }

        $physicalCount->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        broadcast(new PhysicalCountChanged($physicalCount, 'reopened'))->toOthers();

        return back()->with('success', 'Auditoría reabierta correctamente.');
    }

    public function applyAdjustments(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        if ($physicalCount->status !== 'closed') {
            return back()->withErrors([
                'status' => 'Solo se pueden aplicar ajustes de una auditoría finalizada.',
            ]);
        }

        $comparison = $physicalCount->entries()
            ->select(
                'branch_product_id',
                DB::raw('SUM(counted_quantity) as counted_stock'),
                DB::raw('SUM(damaged_quantity) as damaged_stock'),
                DB::raw('SUM(expired_quantity) as expired_stock')
            )
            ->groupBy('branch_product_id')
            ->get();

        foreach ($comparison as $item) {
            $branchProduct = BranchProduct::find($item->branch_product_id);

            if (!$branchProduct) {
                continue;
            }

            $previousStock = (float) $branchProduct->stock;
            $countedStock = (float) $item->counted_stock;
            $damagedStock = (float) $item->damaged_stock;
            $expiredStock = (float) $item->expired_stock;
            $newStock = max(0, $countedStock - $damagedStock - $expiredStock);
            $difference = $newStock - $previousStock;

            if ($difference === 0.0) {
                continue;
            }

            $branchProduct->update(['stock' => $newStock]);

            DB::table('branch_inventory')->updateOrInsert(
                [
                    'branch_id' => $physicalCount->branch_id,
                    'product_id' => $branchProduct->product_id,
                ],
                [
                    'current_stock' => $newStock,
                    'updated_at' => now(),
                ]
            );

            StockMovement::create([
                'branch_product_id' => $branchProduct->id,
                'type' => 'ADJUSTMENT',
                'reason' => 'INVENTORY_DIFFERENCE',
                'quantity' => abs($difference),
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
        }

        $physicalCount->update(['status' => 'applied']);
        broadcast(new PhysicalCountChanged($physicalCount, 'applied'))->toOthers();

        return redirect()
            ->route('audits.physical-counts.show', $physicalCount)
            ->with('success', 'Ajustes aplicados correctamente al inventario.');
    }

    public function exportPdf(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        $physicalCount->load(['branch', 'creator']);
        $comparison = collect($this->buildComparisonRows(
            collect([$physicalCount]),
            $physicalCount->entries()->with(['branchProduct.product', 'user', 'productBatch'])->get(),
            null
        ));

        $summary = [
            'total_entries' => $physicalCount->entries()->count(),
            'total_counted' => $physicalCount->entries()->sum('counted_quantity'),
            'total_damaged' => $physicalCount->entries()->sum('damaged_quantity'),
            'total_expired' => $physicalCount->entries()->sum('expired_quantity'),
            'participants' => $physicalCount->entries()->distinct('user_id')->count('user_id'),
            'audited_products' => $comparison->count(),
        ];

        $pdf = Pdf::loadView('pdf.physical-count', [
            'physicalCount' => $physicalCount,
            'summary' => $summary,
            'comparison' => $comparison,
        ])->setPaper('letter', 'portrait');

        return $pdf->download('conteo-fisico-' . $physicalCount->id . '.pdf');
    }

    public function exportExcel(Request $request, PhysicalCount $physicalCount)
    {
        $this->abortIfCannotManageAudit($request, $physicalCount);

        return Excel::download(
            new PhysicalCountExport($physicalCount),
            'conteo-fisico-' . $physicalCount->id . '.xlsx'
        );
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
                    'audit_name' => $audit?->name ?? 'Sin auditoría',
                    'folio' => $audit?->folio ?? 'Sin folio',
                    'audit_date' => optional($audit?->started_at)->toDateString(),
                    'branch_product_id' => $first->branch_product_id,
                    'product_name' => $first->branchProduct?->product?->name ?? 'Sin producto',
                    'category_name' => $first->branchProduct?->product?->category?->name ?? 'Sin categoría',
                    'subcategory_name' => $first->branchProduct?->product?->subcategory?->name ?? 'Sin subcategoría',
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

    private function scannedProductPayload(
        Request $request,
        PhysicalCount $physicalCount,
        BranchProduct $branchProduct,
        ?string $code = null
    ): array {
        $canViewStock = $this->canViewAuditStock($request);

        $batches = ProductBatch::where('branch_product_id', $branchProduct->id)
            ->orderBy('expiration_date')
            ->get(['id', 'lot_number', 'quantity', 'expiration_date'])
            ->map(function ($batch) use ($canViewStock) {
                $payload = [
                    'id' => $batch->id,
                    'lot_number' => $batch->lot_number,
                    'expiration_date' => optional($batch->expiration_date)->toDateString(),
                ];

                if ($canViewStock) {
                    $payload['quantity'] = $batch->quantity;
                }

                return $payload;
            })
            ->values();

        $payload = [
            'branch_product_id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'name' => $branchProduct->product->name ?? 'Sin producto',
            'barcode' => $branchProduct->barcode ?? $code,
            'scanned_code' => $code ?: ($branchProduct->barcode ?? 'Sin código escaneado'),
            'batches' => $batches,
        ];

        if ($canViewStock) {
            $currentStock = DB::table('branch_inventory')
                ->where('branch_id', $physicalCount->branch_id)
                ->where('product_id', $branchProduct->product_id)
                ->value('current_stock');

            $payload['stock'] = $currentStock ?? $branchProduct->stock;
        }

        return $payload;
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

    private function canManageAudits(?User $user): bool
    {
        return (bool) ($user?->hasPermission('audits.physical-counts.view')
            || $user?->hasPermission('audits.physical-counts.create')
            || $user?->hasPermission('audits.physical-counts.update')
            || $user?->hasPermission('audits.physical-counts.delete'));
    }

    private function canViewReports(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.reports');
    }

    private function canViewAuditStock(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('audits.physical-counts.view-stock');
    }

    private function isAssignedParticipant(?User $user, PhysicalCount $physicalCount): bool
    {
        if (!$user) {
            return false;
        }

        return $physicalCount->participants()
            ->where('users.id', $user->id)
            ->exists();
    }

    private function abortIfCannotCapture(Request $request, PhysicalCount $physicalCount): void
    {
        $user = $request->user();

        if ($this->canManageAudits($user)) {
            return;
        }

        abort_unless(
            $user?->hasPermission('audits.physical-counts.count') && $this->isAssignedParticipant($user, $physicalCount),
            403,
            'No tienes permisos para capturar en esta auditoría.'
        );
    }

    private function abortIfCannotManageAudit(Request $request, PhysicalCount $physicalCount): void
    {
        abort_unless(
            $this->canManageAudits($request->user()),
            403,
            'No tienes permisos para administrar esta auditoría.'
        );
    }

    private function formatLotNumber(string $value): string
    {
        return preg_replace('/-+/', '-', preg_replace('/\s+/', '-', trim($value)));
    }
}
