<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\BranchProduct;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    public function store(Request $request, StockMovementService $stockService)
    {
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
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $branchProduct = BranchProduct::findOrFail($validated['branch_product_id']);

        $movement = $stockService->move(
            $branchProduct,
            $validated['type'],
            $validated['reason'],
            $validated['quantity'],
            $validated['notes'] ?? null,
            Auth::id()
        );



        return back()->with([
            'success' => 'Movimiento de stock registrado correctamente.',
            'movement_id' => $movement->id,
        ]);
    }

    public function index()
    {
        return Inertia::render('Inventory/Movements', [

            'movementsDB' => \App\Models\StockMovement::with([
                'branchProduct.product',
                'branchProduct.branch',
            ])
                ->latest()
                ->get(),

            'branchProductsDB' => \App\Models\BranchProduct::with([
                'product',
                'branch',
            ])
                ->get(),

        ]);
    }
}
