<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class StockMovementController extends Controller
{
    public function store(Request $request, StockMovementService $stockService)
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Validamos el movimiento general
        |--------------------------------------------------------------------------
        |
        | Aquí se valida lo que viene del modal:
        | - producto de sucursal
        | - tipo de movimiento
        | - motivo
        | - cantidad
        | - notas
        | - lotes nuevos para entradas
        | - lotes manuales para salidas
        |
        */

        $validated = $request->validate([
            'branch_product_id' => ['required', 'exists:branch_products,id'],

            'type' => [
                'required',
                Rule::in(['IN', 'OUT', 'ADJUSTMENT']),
            ],

            'reason' => [
                'required',
                Rule::in([
                    'PURCHASE',
                    'SALE',
                    'DAMAGED',
                    'STOLEN',
                    'EXPIRED',
                    'TRANSFER',
                    'MANUAL',
                ]),
            ],

            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | Lotes nuevos
            |--------------------------------------------------------------------------
            |
            | Estos se usan cuando type = IN.
            | Sirven para crear product_batches nuevos.
            |
            */

            'batches' => ['nullable', 'array'],
            'batches.*.lot_number' => ['nullable', 'string', 'max:100'],
            'batches.*.expiration_date' => ['nullable', 'date'],
            'batches.*.quantity' => ['required_with:batches', 'integer', 'min:1'],
            'batches.*.supplier' => ['nullable', 'string', 'max:100'],

            /*
            |--------------------------------------------------------------------------
            | Selección de lote para salidas
            |--------------------------------------------------------------------------
            |
            | FEFO_AUTO:
            | El sistema descuenta automáticamente del lote que caduca primero.
            |
            | MANUAL:
            | El usuario selecciona exactamente qué lote se afecta.
            |
            */

            'batch_allocation_method' => [
                'nullable',
                Rule::in(['FEFO_AUTO', 'MANUAL', 'UNKNOWN']),
            ],

            'manual_batches' => ['nullable', 'array'],

            'manual_batches.*.product_batch_id' => [
                'required_with:manual_batches',
                'exists:product_batches,id',
            ],

            'manual_batches.*.quantity' => [
                'required_with:manual_batches',
                'integer',
                'min:1',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | 2. Validamos que el motivo corresponda al tipo
        |--------------------------------------------------------------------------
        */

        $this->validateReasonByType(
            $validated['type'],
            $validated['reason']
        );

        $branchProduct = BranchProduct::findOrFail($validated['branch_product_id']);

        try {
            /*
            |--------------------------------------------------------------------------
            | 3. Mandamos todo al service
            |--------------------------------------------------------------------------
            |
            | El controller NO altera stock.
            | El controller solo valida y manda la instrucción.
            |
            */

            $movement = $stockService->move(
                branchProduct: $branchProduct,
                type: $validated['type'],
                reason: $validated['reason'],
                quantity: $validated['quantity'],
                notes: $validated['notes'] ?? null,
                userId: Auth::id(),
                batches: $validated['batches'] ?? [],
                batchAllocationMethod: $validated['batch_allocation_method'] ?? 'UNKNOWN',
                manualBatches: $validated['manual_batches'] ?? [],
            );
        } catch (Throwable $e) {
            return back()->withErrors([
                'stock' => $e->getMessage(),
            ]);
        }

        return back()->with([
            'success' => 'Movimiento de stock registrado correctamente.',
            'movement_id' => $movement->id,
        ]);
    }

    public function index()
    {
        return Inertia::render('Inventory/Movements', [
            'movementsDB' => StockMovement::with([
                'branchProduct.product',
                'branchProduct.branch',
                'user',
            ])
                ->latest()
                ->get(),

            'branchProductsDB' => BranchProduct::with([
                'product',
                'branch',
            ])
                ->get(),
        ]);
    }

    private function validateReasonByType(string $type, string $reason): void
    {
        $allowedReasons = match ($type) {
            'IN' => ['PURCHASE', 'TRANSFER', 'MANUAL'],

            'OUT' => [
                'SALE',
                'DAMAGED',
                'STOLEN',
                'EXPIRED',
                'TRANSFER',
                'MANUAL',
            ],

            'ADJUSTMENT' => ['MANUAL'],

            default => [],
        };

        if (! in_array($reason, $allowedReasons, true)) {
            throw ValidationException::withMessages([
                'reason' => 'El motivo seleccionado no corresponde al tipo de movimiento.',
            ]);
        }
    }
}
