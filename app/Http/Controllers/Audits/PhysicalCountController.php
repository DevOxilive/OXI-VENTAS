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
{
    public function index()
    {
        return Inertia::render('Audits/PhysicalCounts/Index', [
            'physicalCounts' => PhysicalCount::with(['branch', 'creator'])
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

    PhysicalCountEntry::create([
        'physical_count_id' => $physicalCount->id,
        'branch_product_id' => $data['branch_product_id'],
        'product_batch_id' => $data['product_batch_id'] ?? null,
        'product_id' => $data['product_id'],
        'user_id' => Auth::id(),
        'scanned_code' => $data['scanned_code'] ?? null,
        'counted_quantity' => $data['counted_quantity'],
        'damaged_quantity' => $data['damaged_quantity'] ?? 0,
        'expired_quantity' => $data['expired_quantity'] ?? 0,
        'expiration_date' => $data['expiration_date'] ?? null,
        'notes' => $data['notes'] ?? null,
    ]);

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
      $nextId = (PhysicalCount::max('id') ?? 0) + 1;

$folio = 'AUD-' . now()->format('Ymd') . '-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

$physicalCount = PhysicalCount::create([
    'folio' => $folio,
    'branch_id' => $data['branch_id'],
    'created_by' => Auth::id(),
    'name' => $data['name'],
    'status' => 'open',
    'started_at' => now(),
]);
broadcast(new PhysicalCountChanged($physicalCount, 'created')
)->toOthers();
        return redirect()
            ->route('audits.physical-counts.index')
            ->with('success', 'Conteo físico creado correctamente.');
    }
    public function exportPdf(PhysicalCount $physicalCount)
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
        ->with('branchProduct')
        ->groupBy('branch_product_id')
        ->get()
        ->map(function ($item) {
            $systemStock = (float) ($item->branchProduct->stock ?? 0);
            $countedStock = (float) $item->counted_stock;
            $difference = $countedStock - $systemStock;

            return [
                'product_name' => $item->branchProduct->name ?? 'Sin producto',
                'system_stock' => $systemStock,
                'counted_stock' => $countedStock,
                'damaged_stock' => (float) $item->damaged_stock,
                'expired_stock' => (float) $item->expired_stock,
                'difference' => $difference,
                'status_label' => $difference < 0
                    ? 'Faltante'
                    : ($difference > 0 ? 'Sobrante' : 'Correcto'),
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
    if ($physicalCount->status === 'applied') {
        return back()->withErrors([
            'status' => 'No se puede reabrir una auditoría que ya fue aplicada al inventario.',
        ]);
    }

    $physicalCount->update([
        'status' => 'open',
        'closed_at' => null,
    ]);

    return redirect()
        ->route('audits.physical-counts.show', $physicalCount)
        ->with('success', 'Auditoría reabierta correctamente.');
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
            DB::raw('SUM(counted_quantity) as counted_stock')
        )
        ->groupBy('branch_product_id')
        ->get();

    foreach ($comparison as $item) {
        $branchProduct = BranchProduct::find($item->branch_product_id);

        if (!$branchProduct) {
            continue;
        }

        $previousStock = (int) $branchProduct->stock;
        $newStock = (int) $item->counted_stock;
        $difference = $newStock - $previousStock;

        if ($difference === 0) {
            continue;
        }

        $branchProduct->update([
            'stock' => $newStock,
        ]);

        StockMovement::create([
            'branch_product_id' => $branchProduct->id,
            'type' => $difference > 0 ? 'in' : 'out',
          'reason' => 'MANUAL',
            'quantity' => abs($difference),
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'user_id' => Auth::id(),
            'notes' => 'Ajuste aplicado desde conteo físico #' . $physicalCount->id,
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
        ->with(['branchProduct', 'productBatch', 'user'])
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

    $comparison = $physicalCount->entries()
        ->select(
            'branch_product_id',
            DB::raw('SUM(counted_quantity) as counted_stock'),
            DB::raw('SUM(damaged_quantity) as damaged_stock'),
            DB::raw('SUM(expired_quantity) as expired_stock')


            
        )
        ->with('branchProduct')
        ->groupBy('branch_product_id')
        ->get()
        ->map(function ($item) {
            $systemStock = (float) ($item->branchProduct->stock ?? 0);
            $countedStock = (float) $item->counted_stock;
            $difference = $countedStock - $systemStock;

            return [
                'branch_product_id' => $item->branch_product_id,
                'product_name' => $item->branchProduct->name ?? 'Sin producto',
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

$summary['missing_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'missing')
    ->count();

$summary['surplus_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'surplus')
    ->count();

$summary['matched_products'] = $comparison
    ->filter(fn ($item) => $item['status'] === 'matched')
    ->count();

    return Inertia::render('Audits/PhysicalCounts/Show', [
        'physicalCount' => $physicalCount,
        'entries' => $entries,
        'summary' => $summary,
        'scannedProduct' => session('scannedProduct'),
        'comparison' => $comparison,





        
        
    ]);
}   public function close(PhysicalCount $physicalCount)
{
    $physicalCount->update([
        'status' => 'closed',
        'closed_at' => now(),
    ]);

    return redirect()
        ->route('audits.physical-counts.show', $physicalCount)
        ->with('success', 'Auditoría finalizada correctamente.');
}
public function scan(Request $request, PhysicalCount $physicalCount)
{
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
        $currentStock = DB::table('branch_inventory')
    ->where('branch_id', $physicalCount->branch_id)
    ->where('product_id', $branchProduct->product_id)
    ->value('current_stock');

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