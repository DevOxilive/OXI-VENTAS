<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Product;
use App\Models\BranchProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index()
{
    return Inertia::render('Systems/Branches', [
        'branches' => Branch::orderBy('name')->get(),
    ]);
}

   public function store(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'color' => ['nullable', 'string', 'max:20'],
    ]);

    DB::transaction(function () use ($data) {
        $branch = Branch::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
            'active' => true,
        ]);

        Product::with(['barcodes'])
            ->where('active', true)
            ->chunk(500, function ($products) use ($branch) {
                foreach ($products as $product) {
                    BranchProduct::updateOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'product_id' => $product->id,
                        ],
                        [
                            'name' => $product->name,
                            'barcode' => $product->barcodes->first()?->code,
                            'category_id' => $product->category_id,
                            'unit' => 'pza',
                            'stock' => 0,
                            'min_stock' => 0,
                            'cost' => $product->cost ?? 0,
                            'price' => $product->sale_price ?? 0,
                            'active' => true,
                        ]
                    );
                }
            });
    });

    return redirect()->back();
}    public function destroy(Branch $branch)
{
    $branch->delete();

    return back()->with('success', 'Sucursal eliminada correctamente');
}
    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:20'],
            'active' => ['boolean'],
        ]);

        $branch->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? null,
            'active' => $data['active'] ?? true,
        ]);

        return redirect()->back();
    }
}