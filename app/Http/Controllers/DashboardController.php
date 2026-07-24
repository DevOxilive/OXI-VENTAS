<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Category;
use App\Services\DashboardMetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardMetricsService $metrics) {}

    public function __invoke(Request $request)
    {
        $user = $request->user()->loadMissing(['role', 'role.permissions', 'permissions', 'branches']);
        $branches = $this->accessibleBranches($user);

        if (! $user->hasPermission('dashboard.executive.view')) {
            return Inertia::render('RoleDashboard', [
                'dashboardUser' => ['name' => $user->name, 'role' => $user->role?->name ?? 'Usuario'],
                'assignedBranches' => $branches->map(fn (Branch $branch) => [
                    'id' => (int) $branch->id, 'name' => $branch->name, 'slug' => $branch->slug, 'color' => $branch->color,
                ])->values(),
            ]);
        }

        $chart = $this->widgetFilters($request, 'chart_', $branches, true);
        $ranking = $this->widgetFilters($request, 'ranking_', $branches);
        $radar = $this->widgetFilters($request, 'radar_', $branches);
        $category = $this->widgetFilters($request, 'category_', $branches, true);
        $chartBranches = $branches->where('id', $chart['branch_id'])->values();
        $categoryBranches = $branches->where('id', $category['branch_id'])->values();
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $selectedCategoryIds = collect(explode(',', (string) $request->input('category_ids', '')))
            ->filter(fn ($id) => ctype_digit((string) $id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $selectedCategories = $categories->whereIn('id', $selectedCategoryIds)->values();
        $chartPayload = $this->metrics->payload($chartBranches->pluck('id'), $chart['start'], $chart['end']);
        $radarProductId = $request->integer('radar_product_id') ?: null;

        return Inertia::render('Dashboard', [
            'branches' => $branches->map(fn (Branch $branch) => ['id' => (int) $branch->id, 'name' => $branch->name])->values(),
            'categories' => $categories->map(fn (Category $category) => ['id' => (int) $category->id, 'name' => $category->name])->values(),
            'chartWidget' => [
                'filters' => $this->publicFilters($chart),
                'summary' => $chartPayload['summary'],
                'series' => $this->metrics->branchSeries($chartBranches, $chart['start'], $chart['end'], $chart['grouping']),
                'limitations' => $chartPayload['limitations'],
            ],
            'rankingWidget' => [
                'filters' => $this->publicFilters($ranking),
                'rows' => $this->metrics->salesRanking($branches, $ranking['start'], $ranking['end']),
            ],
            'radarWidget' => [
                'filters' => $this->publicFilters($radar),
                'product_id' => $radarProductId,
                'product_sales' => $radarProductId
                    ? $this->metrics->productRadar($branches, $radar['start'], $radar['end'], $radarProductId)
                    : null,
            ],
            'categoryWidget' => [
                'filters' => $this->publicFilters($category),
                'selected_ids' => $selectedCategories->pluck('id')->map(fn ($id) => (int) $id)->values(),
                'rows' => $this->metrics->categorySales($categoryBranches, $category['start'], $category['end'], $selectedCategories),
            ],
        ]);
    }

    public function searchProducts(Request $request)
    {
        $user = $request->user()->loadMissing('branches');
        abort_unless($user->hasPermission('dashboard.executive.view'), 403);
        $branches = $this->accessibleBranches($user);
        $filters = $this->widgetFilters($request, 'radar_', $branches);

        return response()->json([
            'products' => $this->metrics->searchProducts($branches->pluck('id'), $filters['start'], $filters['end'], (string) $request->input('search', '')),
        ]);
    }

    private function accessibleBranches($user): Collection
    {
        return $user->accessibleBranchesQuery()->orderBy('branches.name')->get(['branches.id', 'branches.name', 'branches.slug', 'branches.color']);
    }

    private function widgetFilters(Request $request, string $prefix, Collection $branches, bool $needsBranch = false): array
    {
        $timezone = config('app.timezone');
        $now = now($timezone);
        $dateFrom = $request->input($prefix.'date_from');
        $dateTo = $request->input($prefix.'date_to');
        $start = $dateFrom ? Carbon::parse($dateFrom, $timezone)->startOfDay() : $now->copy()->startOfWeek();
        $end = $dateTo ? Carbon::parse($dateTo, $timezone)->endOfDay() : $now->copy()->endOfWeek();

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        $grouping = $request->input($prefix.'grouping', 'day');
        $grouping = in_array($grouping, ['day', 'week', 'month'], true) ? $grouping : 'day';
        $requestedBranch = $request->integer($prefix.'branch_id');
        $branchId = $needsBranch
            ? ($branches->pluck('id')->contains($requestedBranch) ? $requestedBranch : $branches->first()?->id)
            : null;

        return compact('start', 'end', 'grouping', 'branchId') + ['branch_id' => $branchId];
    }

    private function publicFilters(array $filters): array
    {
        return [
            'branch_id' => $filters['branch_id'],
            'date_from' => $filters['start']->toDateString(),
            'date_to' => $filters['end']->toDateString(),
            'grouping' => $filters['grouping'],
        ];
    }
}
