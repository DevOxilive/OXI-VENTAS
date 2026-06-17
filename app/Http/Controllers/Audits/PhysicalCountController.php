<?php

namespace App\Http\Controllers\Audits;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\PhysicalCount;
use App\Models\ProductAlternativeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Models\PhysicalCountEntry;
use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;
use App\Exports\PhysicalCountExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\StockMovement;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Events\PhysicalCountChanged;



class PhysicalCountController extends Controller
{public function index(Request $request)
{
    $branchSlug = $request->query('branch');

    if (!$branchSlug) {
        $firstBranch = Branch::where('active', true)
            ->orderBy('name')
            ->firstOrFail();

        return redirect()->route('audits.physical-counts.index', [
            'branch' => $firstBranch->slug,
        ]);
    }

    $branch = Branch::where('active', true)
        ->where('slug', $branchSlug)
        ->select('id', 'name', 'slug', 'color')
        ->firstOrFail();

    return Inertia::render('Audits/PhysicalCounts/Index', [
        'branch' => $branch,

        'physicalCounts' => PhysicalCount::with(['branch', 'creator'])
            ->where('branch_id', $branch->id)
            ->latest()
            ->get(),

        'branches' => Branch::where('active', true)
            ->select('id', 'name', 'slug', 'color')
            ->orderBy('name')
            ->get(),
    ]);
}
  public function storeEntry(Request $request, PhysicalCount $physicalCount)
{
    if ($physicalCount->status !== 'open') {
        return back()->withErrors([
            'status' => 'Esta auditoría no está abierta. No se pueden registrar conteos.',
        ]);
    }

    $data = $request->validate([
        'branch_product_id' => ['required', 'exists:branch_products,id'],
        'product_batch_id' => ['nullable', 'exists:product_batches,id'],
        'product_id' => ['required', 'exists:products,id'],
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

$entry = PhysicalCountEntry::updateOrCreate(
    [
        'physical_count_id' => $physicalCount->id,
        'branch_product_id' => $data['branch_product_id'],
        'product_batch_id' => $data['product_batch_id'] ?? null,
    ],
    [
        'product_id' => $data['product_id'],
        'user_id' => Auth::id(),
        'scanned_code' => $data['scanned_code'] ?? null,
        'counted_quantity' => $counted,
        'damaged_quantity' => $damaged,
        'expired_quantity' => $expired,
        'expiration_date' => $data['expiration_date'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]
);

broadcast(new PhysicalCountChanged($physicalCount, 'entry_created'))->toOthers();

return redirect()
    ->route('audits.physical-counts.show', $physicalCount)
    ->with('success', 'Conteo guardado correctamente.');
}
public function store(Request $request)
{
    $data = $request->validate([
        'branch_id' => ['required', 'exists:branches,id'],
        'name' => ['required', 'string', 'max:255'],
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

    broadcast(new PhysicalCountChanged($physicalCount, 'created'))->toOthers();

    return redirect()
        ->route('audits.physical-counts.index', [
            'branch' => $branch->slug,
        ])
        ->with('success', 'Conteo físico creado correctamente.');
}    public function exportPdf(PhysicalCount $physicalCount)
{
    $physicalCount->load(['branch', 'creator']);

    $summary = [
        'total_entries' => $physicalCount->entries()->count(),
        'total_counted' => $physicalCount->entries()->sum('counted_quantity'),
        'total_damaged' => $physicalCount->entries()->sum('damaged_quantity'),
        'total_expired' => $physicalCount->entries()->sum('expired_quantity'),
        'participants' => $physicalCount->entries()->distinct('user_id')->count('user_id'),
    ];

  $comparison = $physicalCount->entries()
    ->select(
        'branch_product_id',
        DB::raw('SUM(counted_quantity) as counted_stock'),
        DB::raw('SUM(damaged_quantity) as damaged_stock'),
        DB::raw('SUM(expired_quantity) as expired_stock')
    )
    ->with('branchProduct.product')
    ->groupBy('branch_product_id')
    ->get()
    ->map(function ($item) {
        $systemStock = (float) ($item->branchProduct->stock ?? 0);
        $countedStock = (float) $item->counted_stock;
        $difference = $countedStock - $systemStock;

        return [
            'branch_product_id' => $item->branch_product_id,

            'product_name' => $item->branchProduct?->product?->name
                ?? 'Sin producto',

            'system_stock' => $systemStock,
            'counted_stock' => $countedStock,
            'damaged_stock' => (float) $item->damaged_stock,
            'expired_stock' => (float) $item->expired_stock,
            'difference' => $difference,
            'status' => $difference < 0
                ? 'missing'
                : ($difference > 0 ? 'surplus' : 'matched'),
        ];
    })
    ->values();

    $summary['audited_products'] = $comparison->count();

    $pdf = Pdf::loadView('pdf.physical-count', [
        'physicalCount' => $physicalCount,
        'summary' => $summary,
        'comparison' => $comparison,
    ])->setPaper('letter', 'portrait');
   

    return $pdf->download('conteo-fisico-' . $physicalCount->id . '.pdf');
}
 public function reopen(PhysicalCount $physicalCount)
{
    if ($physicalCount->status !== 'closed') {
        return back()->withErrors([
            'status' => 'Solo auditorías cerradas pueden reabrirse.',
        ]);
    }

    $physicalCount->update([
        'status' => 'open',
        'closed_at' => null,
    ]);

    return back()->with('success', 'Auditoría reabierta correctamente.');
}
    public function exportExcel(PhysicalCount $physicalCount)
{
    $fileName = 'conteo-fisico-' . $physicalCount->id . '.xlsx';

    return Excel::download(
        new PhysicalCountExport($physicalCount),
        $fileName
    );
}
public function applyAdjustments(PhysicalCount $physicalCount)
{
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

        if ($difference === 0) {
            continue;
        }

        $branchProduct->update([
            'stock' => $newStock,
        ]);
        DB::table('branch_inventory')
    ->updateOrInsert(
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

    $physicalCount->update([
        'status' => 'applied',
    ]);

    return redirect()
        ->route('audits.physical-counts.show', $physicalCount)
        ->with('success', 'Ajustes aplicados correctamente al inventario.');
}
public function show(PhysicalCount $physicalCount)
{
    $physicalCount->load(['branch', 'creator']);

    $entries = $physicalCount->entries()
      ->with(['branchProduct.product', 'productBatch', 'user'])
        ->latest()
        ->take(20)
        ->get();

    $summary = [
        'total_entries' => $physicalCount->entries()->count(),
        'total_counted' => $physicalCount->entries()->sum('counted_quantity'),
        'total_damaged' => $physicalCount->entries()->sum('damaged_quantity'),
        'total_expired' => $physicalCount->entries()->sum('expired_quantity'),
        'participants' => $physicalCount->entries()->distinct('user_id')->count('user_id'),
        
        
    ];
    $summary['participant_names'] = $physicalCount->entries()
    ->with('user:id,name')
    ->get()
    ->pluck('user.name')
    ->filter()
    ->unique()
    ->values();

   $comparison = $physicalCount->entries()
    ->select(
        'branch_product_id',
        DB::raw('SUM(counted_quantity) as counted_stock'),
        DB::raw('SUM(damaged_quantity) as damaged_stock'),
        DB::raw('SUM(expired_quantity) as expired_stock')
    )
    ->with('branchProduct.product')
    ->groupBy('branch_product_id')
    ->get()
    ->map(function ($item) {
        $systemStock = (float) ($item->branchProduct->stock ?? 0);
        $countedStock = (float) $item->counted_stock;
        $damagedStock = (float) $item->damaged_stock;
        $expiredStock = (float) $item->expired_stock;

        $sellableStock = max(0, $countedStock - $damagedStock - $expiredStock);
        $difference = $sellableStock - $systemStock;

        return [
            'branch_product_id' => $item->branch_product_id,
            'product_name' => $item->branchProduct?->product?->name ?? 'Sin producto',
            'system_stock' => $systemStock,
            'counted_stock' => $countedStock,
            'damaged_stock' => $damagedStock,
            'expired_stock' => $expiredStock,
            'sellable_stock' => $sellableStock,
            'difference' => $difference,
            'status' => $difference < 0
                ? 'missing'
                : ($difference > 0 ? 'surplus' : 'matched'),
        ];
    })
    ->values();
    $summary['audited_products'] = $comparison->count();

$summary['missing_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'missing')
    ->count();

$summary['surplus_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'surplus')
    ->count();

$summary['matched_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'matched')
    ->count();
 $totalProductsInBranch = BranchProduct::where(
    'branch_id',
    $physicalCount->branch_id
)->count();
$summary['total_products_in_branch'] = $totalProductsInBranch;

$summary['pending_products'] = max(
    0,
    $totalProductsInBranch - $summary['audited_products']
);

$summary['progress_percentage'] = $totalProductsInBranch > 0
    ? round(
        ($summary['audited_products'] / $totalProductsInBranch) * 100,
        2
    )
    : 0;

    return Inertia::render('Audits/PhysicalCounts/Show', [
        'physicalCount' => $physicalCount,
        'entries' => $entries,
        'summary' => $summary,
        'scannedProduct' => session('scannedProduct'),
        'comparison' => $comparison,





        
        
    ]);
}  public function close(PhysicalCount $physicalCount)
{
    if ($physicalCount->status !== 'open') {
        return back()->withErrors([
            'status' => 'Solo auditorías abiertas pueden cerrarse.'
        ]);
    }

    $physicalCount->update([
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    return back()->with('success', 'Auditoría cerrada correctamente.');
}
public function destroyEntry(PhysicalCountEntry $entry)
{
    $entry->load('physicalCount');

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
public function updateEntry(Request $request, PhysicalCountEntry $entry)
{
    $entry->load('physicalCount');

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

public function scan(Request $request, PhysicalCount $physicalCount)
{
if ($physicalCount->status !== 'open') {
    return back()->withErrors([
        'status' => 'Esta auditoría no está abierta. No se pueden escanear productos.',
    ]);
}

    $data = $request->validate([
        'code' => ['required', 'string', 'max:255'],
    ]);

    $code = trim($data['code']);

    $branchProduct = null;

    // 1. Buscar primero en la tabla barcodes
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

    // 2. Si no se encontró, buscar en códigos alternativos
    if (!$branchProduct) {
        $alternativeCode = ProductAlternativeCode::where('code', $code)->first();

        if ($alternativeCode) {
            $branchProduct = BranchProduct::with('product')
                ->where('branch_id', $physicalCount->branch_id)
                ->where('product_id', $alternativeCode->product_id)
                ->first();
        }
    }

    // 3. Si no existe en esa sucursal, regresar error
    if (!$branchProduct) {
        return back()->withErrors([
            'code' => 'No se encontró un producto con ese código en la sucursal auditada.',
        ]);
    }

    // 4. Traer lotes
    $batches = ProductBatch::where('branch_product_id', $branchProduct->id)
        ->orderBy('expiration_date')
        ->get([
            'id',
            'lot_number',
            'quantity',
            'expiration_date',
        ]);

        // 4. Trae EL STOCK
       

    // 5. Regresar producto encontrado
    return back()->with([
        'scannedProduct' => [
            'branch_product_id' => $branchProduct->id,
            'product_id' => $branchProduct->product_id,
            'name' => $branchProduct->product->name ?? 'Sin producto',
            'barcode' => $code,
      'stock' => $currentStock ?? $branchProduct->stock,
            'scanned_code' => $code,
            'batches' => $batches,
        ],
    ]);
}}