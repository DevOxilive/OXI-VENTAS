<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\DashboardMetricsService;
use App\Support\LocalDateTime;
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

        // Durante la temporada navideña el inicio es la bienvenida con los
        // accesos de cada rol. Así ningún usuario recibe el tablero de gráficas.
        return Inertia::render('RoleDashboard', [
            'dashboardUser' => ['name' => $user->name, 'role' => $user->role?->name ?? 'Usuario'],
            'assignedBranches' => $branches->map(fn (Branch $branch) => [
                'id' => (int) $branch->id, 'name' => $branch->name, 'slug' => $branch->slug, 'color' => $branch->color,
            ])->values(),
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
        $timezone = LocalDateTime::TIMEZONE;
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

        return compact('grouping', 'branchId') + [
            'start' => $start->utc(),
            'end' => $end->utc(),
            'branch_id' => $branchId,
        ];
    }

    private function publicFilters(array $filters): array
    {
        return [
            'branch_id' => $filters['branch_id'],
            'date_from' => LocalDateTime::format($filters['start'], 'Y-m-d'),
            'date_to' => LocalDateTime::format($filters['end'], 'Y-m-d'),
            'grouping' => $filters['grouping'],
        ];
    }
}
