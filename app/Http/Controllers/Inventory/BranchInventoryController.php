<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchInventoryController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/BranchInventory', [

            'branchProductsDB' => BranchProduct::with([
                'branch',
                'product',
            ])->get(),

            'productsDB' => Product::where('active', true)->get(),

            'branchesDB' => Branch::where('active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],

            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
        ]);

        BranchProduct::create([
            'branch_id' => $validated['branch_id'],
            'product_id' => $validated['product_id'],

            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'min_stock' => $validated['min_stock'],

            'active' => true,
        ]);

        return back()->with('success', 'Producto asignado a sucursal correctamente.');
    }
}
