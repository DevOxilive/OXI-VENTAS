<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
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
        */

        $validated = $request->validate([
            'branch_product_id' => ['required', 'exists:branch_products,id'],

            'type' => [
                'required',
                Rule::in([
                    StockMovement::TYPE_IN,
                    StockMovement::TYPE_OUT,
                    StockMovement::TYPE_ADJUSTMENT,
                ]),
            ],

            'reason' => [
                'required',
                Rule::in([
                    StockMovement::REASON_PURCHASE,
                    StockMovement::REASON_SALE,
                    StockMovement::REASON_DAMAGED,
                    StockMovement::REASON_EXPIRED,
                    StockMovement::REASON_INVENTORY_DIFFERENCE,
                ]),
            ],

            'quantity' => ['required', 'numeric', 'min:0.001'],
            'notes' => ['nullable', 'string', 'max:500'],

            /*
            |--------------------------------------------------------------------------
            | Lotes nuevos para entradas
            |--------------------------------------------------------------------------
            */

            'batches' => ['nullable', 'array'],
            'batches.*.lot_number' => ['required_with:batches', 'string', 'max:100'],
            'batches.*.expiration_date' => ['required_with:batches', 'date'],
            'batches.*.received_at' => ['required_with:batches', 'date'],
            'batches.*.quantity' => [
                'required_with:batches',
                'numeric',
                'min:0.001',
            ],
            'batches.*.supplier' => ['nullable', 'string', 'max:100'],

            /*
            |--------------------------------------------------------------------------
            | Selección manual de lotes para salidas
            |--------------------------------------------------------------------------
            */

            'batch_allocation_method' => [
                'nullable',
                Rule::in([
                    StockMovementBatch::ALLOCATION_MANUAL,
                ]),
            ],

            'manual_batches' => ['nullable', 'array'],

            'manual_batches.*.id' => [
                'required_with:manual_batches',
                'exists:product_batches,id',
            ],

            'manual_batches.*.quantity' => [
                'required_with:manual_batches',
                'numeric',
                'min:0.001',
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
            */

            $movement = $stockService->move(
                branchProduct: $branchProduct,
                type: $validated['type'],
                reason: $validated['reason'],
                quantity: (float) $validated['quantity'],
                notes: $validated['notes'] ?? null,
                userId: Auth::id(),
                batches: $validated['batches'] ?? [],
                batchAllocationMethod: $validated['batch_allocation_method']
                ?? StockMovementBatch::ALLOCATION_MANUAL,
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
            StockMovement::TYPE_IN => [
                StockMovement::REASON_PURCHASE,
            ],

            StockMovement::TYPE_OUT => [
                StockMovement::REASON_SALE,
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
            ],

            StockMovement::TYPE_ADJUSTMENT => [
                StockMovement::REASON_INVENTORY_DIFFERENCE,
            ],

            default => [],
        };

        if (!in_array($reason, $allowedReasons, true)) {
            throw ValidationException::withMessages([
                'reason' => 'El motivo seleccionado no corresponde al tipo de movimiento.',
            ]);
        }
    }
}
