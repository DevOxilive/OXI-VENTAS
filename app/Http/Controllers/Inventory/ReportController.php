<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchProduct;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Branch $branch)
    {
        $branchProducts = BranchProduct::with(['branch', 'product'])
            ->where('branch_id', $branch->id)
            ->where('active', true)
            ->get();

        return Inertia::render('Inventory/Reports', [
            'currentBranch' => $branch,

            'branches' => Branch::where('active', true)->get(),

            'summary' => [
                'total_products' => $branchProducts->count(),
                'total_stock' => $branchProducts->sum('stock'),
                'inventory_value' => $branchProducts->sum(fn($item) => $item->stock * $item->price),
                'low_stock' => $branchProducts->filter(fn($item) => $item->stock <= $item->min_stock)->count(),
                'out_of_stock' => $branchProducts->where('stock', 0)->count(),
            ],

            'branchReports' => [[
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'products_count' => $branchProducts->count(),
                'total_stock' => $branchProducts->sum('stock'),
                'inventory_value' => $branchProducts->sum(fn($item) => $item->stock * $item->price),
                'low_stock' => $branchProducts->filter(fn($item) => $item->stock <= $item->min_stock)->count(),
                'out_of_stock' => $branchProducts->where('stock', 0)->count(),
            ]],
        ]);
    }
}
