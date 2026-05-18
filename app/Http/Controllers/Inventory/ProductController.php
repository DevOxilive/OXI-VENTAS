<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Inventory/Home', [

            'productsDB' => Product::with('category')->get(),

            'categoriesDB' => Category::all(),

            'branchesDB' => [],

        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'active' => ['boolean'],
        ]);

        Product::create($data);

        return back()->with(
            'success',
            'Producto creado correctamente'
        );
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'active' => ['boolean'],
        ]);

        $product->update($data);

        return back()->with(
            'success',
            'Producto actualizado correctamente'
        );
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return back()->with(
            'success',
            'Producto eliminado correctamente'
        );
    }
}