<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Models\StockMovement;
use App\Models\StockMovementBatch;
use App\Services\StockMovementService;
use App\Support\FlexibleSearch;
use App\Support\TablePagination;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
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
        if (! $request->user()->hasBranchAccess((int) $branchProduct->branch_id)) {
            throw new AuthorizationException('No tienes acceso al inventario de esta sucursal.');
        }

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

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'type' => ['nullable', Rule::in([
                StockMovement::TYPE_IN,
                StockMovement::TYPE_OUT,
                StockMovement::TYPE_ADJUSTMENT,
            ])],
            'reason' => ['nullable', Rule::in([
                StockMovement::REASON_PURCHASE,
                StockMovement::REASON_SALE,
                StockMovement::REASON_DAMAGED,
                StockMovement::REASON_EXPIRED,
                StockMovement::REASON_OTHER,
                StockMovement::REASON_INVENTORY_DIFFERENCE,
            ])],
            'per_page' => ['nullable', 'integer'],
        ]);
        $filters['search'] = trim((string) ($filters['search'] ?? ''));
        $filters['per_page'] = TablePagination::resolvePerPage($request, 50);

        $user = $request->user()->loadMissing(['role', 'branches']);
        $accessibleBranches = $user->accessibleBranchesQuery()
            ->select(['branches.id', 'branches.name'])
            ->orderBy('branches.name')
            ->get();
        $accessibleBranchIds = $accessibleBranches->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (($filters['branch_id'] ?? null) && ! in_array((int) $filters['branch_id'], $accessibleBranchIds, true)) {
            throw new AuthorizationException('No tienes acceso a la sucursal seleccionada.');
        }

        $movements = StockMovement::query()
            ->select([
                'stock_movements.id',
                'stock_movements.branch_product_id',
                'stock_movements.type',
                'stock_movements.reason',
                'stock_movements.quantity',
                'stock_movements.previous_stock',
                'stock_movements.new_stock',
                'stock_movements.created_at',
            ])
            ->with([
                'branchProduct:id,branch_id,product_id',
                'branchProduct.product:id,name',
                'branchProduct.branch:id,name',
            ])
            ->whereHas('branchProduct', fn ($query) => $query
                ->whereIn('branch_id', $accessibleBranchIds)
                ->when($filters['branch_id'] ?? null, fn ($branchQuery, $branchId) => $branchQuery
                    ->where('branch_id', $branchId)))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['reason'] ?? null, fn ($query, $reason) => $query->where('reason', $reason));

        FlexibleSearch::apply($movements, $filters['search'], function ($query, $phrase, $terms) {
            FlexibleSearch::orWhereHasColumns($query, 'branchProduct', ['barcode'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'branchProduct.product', ['name'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'branchProduct.product.barcodes', ['code'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'branchProduct.branch', ['name'], $phrase, $terms);
        });

        return Inertia::render('Inventory/Movements', [
            'movementsDB' => $movements
                ->latest()
                ->paginate($filters['per_page'])
                ->withQueryString(),
            'filters' => $filters,
            'branchOptions' => $accessibleBranches
                ->map(fn ($branch) => ['value' => $branch->id, 'label' => $branch->name])
                ->values(),
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search' => ['required', 'string', 'max:100'],
        ]);
        $term = trim($data['search']);

        if ($term === '') {
            return response()->json(['products' => []]);
        }

        $user = $request->user()->loadMissing(['role', 'branches']);
        $accessibleBranchIds = $user->accessibleBranchIds();

        $products = BranchProduct::query()
            ->select([
                'branch_products.id',
                'branch_products.branch_id',
                'branch_products.product_id',
                'branch_products.barcode',
                'branch_products.stock',
            ])
            ->with([
                'product:id,name',
                'product.barcodes:id,product_id,code',
                'branch:id,name',
            ])
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('status', BranchProduct::STATUS_ACTIVE)
            ->whereHas('product', fn ($query) => $query->where('active', true));

        FlexibleSearch::apply($products, $term, function ($query, $phrase, $terms) {
            FlexibleSearch::orWhereColumns($query, ['branch_products.barcode'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'product', ['name'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'product.barcodes', ['code'], $phrase, $terms);
            FlexibleSearch::orWhereHasColumns($query, 'branch', ['name'], $phrase, $terms);
        });

        return response()->json([
            'products' => $products
                ->orderBy(
                    \App\Models\Product::query()
                        ->select('name')
                        ->whereColumn('products.id', 'branch_products.product_id')
                        ->limit(1)
                )
                ->limit(20)
                ->get()
                ->map(fn (BranchProduct $branchProduct) => [
                    'id' => $branchProduct->id,
                    'name' => $branchProduct->product?->name ?? 'Producto sin nombre',
                    'branch' => $branchProduct->branch?->name ?? 'Sucursal no disponible',
                    'code' => $branchProduct->barcode
                        ?? $branchProduct->product?->barcodes?->first()?->code,
                    'stock' => (float) $branchProduct->stock,
                ])
                ->values(),
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
            StockMovement::TYPE_IN => 'inventory.branches.stock-in',
            StockMovement::TYPE_OUT => 'inventory.branches.stock-out',
            StockMovement::TYPE_ADJUSTMENT => 'inventory.branches.stock-adjust',
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
