<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Services\StockMovementService;
use Illuminate\Auth\Access\AuthorizationException;
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
                    StockMovement::REASON_OTHER,
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

            'branch_allocations' => ['nullable', 'array'],
            'branch_allocations.*.branch_id' => ['required_with:branch_allocations', 'exists:branches,id'],
            'branch_allocations.*.quantity' => ['required_with:branch_allocations', 'numeric', 'min:0.001'],

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

        $this->authorizeMovement($request, $validated['type']);

        $branchProduct = BranchProduct::findOrFail($validated['branch_product_id']);

        try {
            $hasBranchAllocations = $validated['type'] === StockMovement::TYPE_IN
                && collect($validated['branch_allocations'] ?? [])->isNotEmpty();

            /*
            |--------------------------------------------------------------------------
            | 3. Mandamos todo al service
            |--------------------------------------------------------------------------
            */

            if ($hasBranchAllocations) {
                $this->validateBranchAllocationTotal(
                    (float) $validated['quantity'],
                    $validated['branch_allocations'] ?? [],
                );

                $movement = $stockService->distributeIncoming(
                    sourceBranchProduct: $branchProduct,
                    reason: $validated['reason'],
                    quantity: (float) $validated['quantity'],
                    notes: $validated['notes'] ?? null,
                    userId: Auth::id(),
                    batch: ($validated['batches'] ?? [])[0] ?? [],
                    branchAllocations: $validated['branch_allocations'] ?? [],
                );
            } else {
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
            }
        } catch (Throwable $e) {
            return back()->withErrors([
                'stock' => $e->getMessage(),
            ]);
        }

        $movementId = $movement instanceof StockMovement
            ? $movement->id
            : collect($movement)->pluck('movement_id')->filter()->first();

        return back()->with([
            'success' => 'Movimiento de stock registrado correctamente.',
            'movement_id' => $movementId,
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
                StockMovement::REASON_OTHER,
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

    private function authorizeMovement(Request $request, string $type): void
    {
        $user = $request->user();

        $requiredPermission = match ($type) {
            StockMovement::TYPE_IN => 'inventory.branches.create',
            StockMovement::TYPE_OUT,
            StockMovement::TYPE_ADJUSTMENT => 'inventory.branches.update',
            default => null,
        };

        if (!$requiredPermission || !$user?->hasPermission($requiredPermission)) {
            throw new AuthorizationException('No tienes permisos para registrar este movimiento.');
        }
    }

    private function validateBranchAllocationTotal(float $expectedQuantity, array $allocations): void
    {
        $total = collect($allocations)
            ->sum(fn ($allocation) => (float) ($allocation['quantity'] ?? 0));

        if (round($total, 3) !== round($expectedQuantity, 3)) {
            throw ValidationException::withMessages([
                'branch_allocations' => 'La suma asignada a sucursales debe coincidir con la cantidad total.',
            ]);
        }
    }
}
