<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\InventoryReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request, Branch $branch, InventoryReportService $reportService)
    {
        $filters = $this->resolveFilters($request);
        $activeReport = $request->input('report', 'dashboard');

        $reports = [
            'movements' => [],
            'expirations' => [],
            'rotation' => [],
            'attentionProducts' => [],
        ];

        if ($activeReport === 'movements') {
            $reports['movements'] = $reportService->movements($branch, $filters);
        }

        if ($activeReport === 'expirations') {
            $reports['expirations'] = $reportService->expirations($branch, $filters);
        }

        if ($activeReport === 'rotation') {
            $reports['rotation'] = $reportService->rotation($branch, $filters);
        }

        if ($activeReport === 'attention') {
            $reports['attentionProducts'] = $reportService->attentionProducts($branch);
        }

        return Inertia::render('Inventory/Reports', [
            'currentBranch' => $branch,
            'activeReport' => $activeReport,
            'filters' => $filters,
            'catalogs' => [
                'movementTypes' => [
                    'IN',
                    'OUT',
                    'ADJUSTMENT',
                ],
                'movementReasons' => [
                    'PURCHASE',
                    'SALE',
                    'DAMAGED',
                    'EXPIRED',
                    'INVENTORY_DIFFERENCE',
                ],
            ],
            'summary' => $reportService->summary($branch),
            'reports' => $reports,
        ]);
    }

    private function resolveFilters(Request $request): array
    {
        return [
            'period' => $request->input('period', '30_days'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'product_id' => $request->input('product_id'),
            'category_id' => $request->input('category_id'),
            'user_id' => $request->input('user_id'),
            'movement_type' => $request->input('movement_type'),
            'movement_reason' => $request->input('movement_reason'),
        ];
    }
}