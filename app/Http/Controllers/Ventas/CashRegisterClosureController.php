<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashRegisterClosure;
use App\Models\Sale;
use App\Models\TicketTemplate;
use App\Support\SystemPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CashRegisterClosureController extends Controller
{
    use AuthorizesBranchAccess;

    public function index(Request $request)
    {
        $user = $request->user()->loadMissing(['role', 'branches']);
        $branches = $this->accessibleBranches($request);
        $isAdminSelector = $user->hasPermission(SystemPermission::BRANCHES_ACCESS_ALL) && !$request->filled('branch');

        if ($isAdminSelector) {
            return Inertia::render('Ventas/CashRegisterClosures', [
                'selectorMode' => true,
                'branch' => null,
                'branchesDB' => $branches->map(fn (Branch $branch) => $this->mapBranchSelector($branch))->values(),
                'current' => null,
            ]);
        }

        $branch = $this->resolveBranchForCut($request, $branches);
        $current = $this->buildCurrentCut($branch, null, $request->query('cash_box'));

        return Inertia::render('Ventas/CashRegisterClosures', [
            'selectorMode' => false,
            'branch' => $this->mapBranch($branch),
            'branchesDB' => $branches->map(fn (Branch $branch) => $this->mapBranchSelector($branch))->values(),
            'current' => $current,
            'ticketTemplate' => $this->cashClosureTicketTemplate(),
        ]);
    }

    public function reports(Request $request, ?Branch $branch = null)
    {
        $branches = $this->accessibleBranches($request);

        if ($branch) {
            $this->abortIfUserCannotAccessBranch($request, $branch);
        }

        if (!$branch && $branches->count() > 1) {
            return Inertia::render('Ventas/CashRegisterClosureReports', [
                'selectorMode' => true,
                'currentBranch' => null,
                'branchesDB' => $branches
                    ->map(fn (Branch $availableBranch) => $this->mapCashClosureReportBranch($availableBranch))
                    ->values(),
                'closures' => $this->emptyClosurePaginator(),
                'summary' => $this->emptyClosureSummary(),
            ]);
        }

        $branch = $branch ?: $branches->first();
        abort_unless($branch, 404, 'No hay sucursales disponibles.');

        $branchIds = [$branch->id];

        $closures = CashRegisterClosure::query()
            ->with(['branch:id,name,slug', 'user:id,name'])
            ->whereIn('branch_id', $branchIds)
            ->latest('period_end')
            ->paginate(15)
            ->through(fn (CashRegisterClosure $closure) => $this->mapClosure($closure));

        $summary = CashRegisterClosure::query()
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('COUNT(*) as cuts_count')
            ->selectRaw('COALESCE(SUM(sales_count), 0) as sales_count')
            ->selectRaw('COALESCE(SUM(sales_total), 0) as sales_total')
            ->selectRaw('COALESCE(SUM(expected_cash), 0) as expected_cash')
            ->selectRaw('COALESCE(SUM(card_total), 0) as card_total')
            ->selectRaw('COALESCE(SUM(other_total), 0) as other_total')
            ->selectRaw('COALESCE(SUM(recharge_total), 0) as recharge_total')
            ->selectRaw('COALESCE(SUM(expected_drawer_cash), 0) as expected_drawer_cash')
            ->selectRaw('COALESCE(SUM(counted_cash), 0) as counted_cash')
            ->selectRaw('COALESCE(SUM(cash_difference), 0) as cash_difference')
            ->first();

        return Inertia::render('Ventas/CashRegisterClosureReports', [
            'selectorMode' => false,
            'currentBranch' => $branch ? $this->mapBranch($branch) : null,
            'branchesDB' => $branches
                ->map(fn (Branch $availableBranch) => $this->mapCashClosureReportBranch($availableBranch))
                ->values(),
            'closures' => $closures,
            'summary' => $this->mapClosureSummary($summary),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'exists:branches,id'],
            'cash_box_number' => ['required', 'string', 'max:10'],
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'cash_left' => ['required', 'numeric', 'min:0'],
            'counted_card' => ['required', 'numeric', 'min:0'],
            'denomination_breakdown' => ['required', 'array'],
            'denomination_breakdown.*' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $branch = Branch::query()->findOrFail($data['branch_id']);
        $this->abortIfUserCannotAccessBranch($request, $branch);

        $closure = DB::transaction(function () use ($request, $branch, $data) {
            $current = $this->buildCurrentCut($branch, now(), $data['cash_box_number']);
            $denominations = $this->normalizeDenominations($data['denomination_breakdown'] ?? []);
            $countedCash = $this->sumDenominations($denominations);
            $countedCard = round((float) $data['counted_card'], 2);
            $expectedDrawerCash = round((float) $current['expected_cash'], 2);

            return CashRegisterClosure::create([
                'folio' => $this->nextAnnualFolio(now()->year),
                'branch_id' => $branch->id,
                'user_id' => $request->user()->id,
                'cash_box_number' => $data['cash_box_number'],
                'period_start' => $current['period_start_raw'],
                'period_end' => $current['period_end_raw'],
                'sales_count' => $current['sales_count'],
                'sales_total' => $current['sales_total'],
                'expected_cash' => $current['expected_cash'],
                'card_total' => $current['card_total'],
                'other_total' => 0,
                'recharge_total' => 0,
                'expected_drawer_cash' => $expectedDrawerCash,
                'counted_cash' => $countedCash,
                'cash_left' => round((float) $data['cash_left'], 2),
                'counted_card' => $countedCard,
                'cash_difference' => round($countedCash - $expectedDrawerCash, 2),
                'card_difference' => round($countedCard - (float) $current['card_total'], 2),
                'payment_breakdown' => $current['payment_breakdown'],
                'denomination_breakdown' => $denominations,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        $closure->load(['branch:id,name,slug', 'user:id,name']);

        return to_route('inventory.branches.reports.cash-closures', ['branch' => $branch->id])->with([
            'success' => "Corte {$closure->folio} registrado correctamente.",
            'cash_closure_print_jobs' => $this->buildClosurePrintJobs($closure),
        ]);
    }

    public function update(Request $request, CashRegisterClosure $closure)
    {
        $closure->loadMissing('branch');
        $this->abortIfUserCannotAccessBranch($request, $closure->branch);

        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'cash_left' => ['required', 'numeric', 'min:0'],
            'counted_card' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $countedCash = round((float) $data['counted_cash'], 2);
        $cashLeft = round((float) $data['cash_left'], 2);
        $countedCard = round((float) $data['counted_card'], 2);

        $closure->update([
            'counted_cash' => $countedCash,
            'cash_left' => $cashLeft,
            'counted_card' => $countedCard,
            'cash_difference' => round($countedCash - (float) $closure->expected_drawer_cash, 2),
            'card_difference' => round($countedCard - (float) $closure->card_total, 2),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', "Corte {$closure->folio} actualizado correctamente.");
    }

    public function destroy(Request $request, CashRegisterClosure $closure)
    {
        $closure->loadMissing('branch');
        $this->abortIfUserCannotAccessBranch($request, $closure->branch);

        $folio = $closure->folio;
        $closure->delete();

        return back()->with('success', "Corte {$folio} eliminado correctamente.");
    }

    private function buildCurrentCut(Branch $branch, $periodEnd = null, ?string $cashBoxNumber = null): array
    {
        $periodEnd = $periodEnd ?: now();
        $lastClosureQuery = CashRegisterClosure::query()
            ->where('branch_id', $branch->id)
            ->latest('period_end');

        if ($cashBoxNumber) {
            $lastClosureQuery->where('cash_box_number', $cashBoxNumber);
        }

        $lastClosure = $lastClosureQuery->first();
        $periodStart = $lastClosure?->period_end?->copy() ?? now()->startOfDay();

        $sales = Sale::query()
            ->with(['paymentMethod:id,name', 'employee.user:id,employee_id,name'])
            ->where('branch_id', $branch->id)
            ->where('status', 'completed')
            ->where('date', '>', $periodStart)
            ->where('date', '<=', $periodEnd)
            ->orderBy('date')
            ->get();

        $cashBoxNumber = $cashBoxNumber
            ?: (string) ($sales->sortByDesc('date')->first()?->cash_box_number ?: '1');

        $sales = $sales
            ->filter(fn (Sale $sale) => (string) ($sale->cash_box_number ?: '1') === (string) $cashBoxNumber)
            ->values();

        $paymentBreakdown = $sales
            ->groupBy(fn (Sale $sale) => $sale->paymentMethod?->name ?? 'Sin metodo')
            ->map(fn (Collection $group, string $method) => [
                'method' => $method,
                'sales_count' => $group->count(),
                'total' => round((float) $group->sum('total'), 2),
                'expected_cash' => $this->isCashMethod($method) ? round((float) $group->sum('total'), 2) : 0,
                'type' => $this->paymentType($method),
            ])
            ->values();

        $expectedCash = $paymentBreakdown->sum('expected_cash');
        $cardTotal = $paymentBreakdown
            ->where('type', 'card')
            ->sum('total');

        return [
            'cash_box_number' => $cashBoxNumber,
            'period_start_raw' => $periodStart,
            'period_end_raw' => $periodEnd,
            'period_start' => $periodStart->format('d/m/Y H:i'),
            'period_end' => $periodEnd->format('d/m/Y H:i'),
            'sales_count' => $sales->count(),
            'sales_total' => round((float) $sales->sum('total'), 2),
            'expected_cash' => round((float) $expectedCash, 2),
            'card_total' => round((float) $cardTotal, 2),
            'other_total' => 0,
            'payment_breakdown' => $paymentBreakdown->all(),
            'active_users' => $this->activeUsers($sales),
            'sales' => $sales->map(fn (Sale $sale) => $this->mapSale($sale))->values()->all(),
        ];
    }

    private function nextAnnualFolio(int $year): string
    {
        $nextNumber = CashRegisterClosure::query()
            ->whereYear('period_end', $year)
            ->count() + 1;

        do {
            $folio = "{$nextNumber}-{$year}";
            $exists = CashRegisterClosure::query()
                ->where('folio', $folio)
                ->exists();

            $nextNumber += 1;
        } while ($exists);

        return $folio;
    }

    private function activeUsers(Collection $sales): array
    {
        return $sales
            ->groupBy(fn (Sale $sale) => $this->cashierName($sale))
            ->map(function (Collection $group, string $name) {
                $lastSale = $group->sortByDesc('date')->first();

                return [
                    'name' => $name,
                    'sales_count' => $group->count(),
                    'sales_total' => round((float) $group->sum('total'), 2),
                    'last_sale_at' => $lastSale?->date?->format('d/m/Y H:i'),
                ];
            })
            ->values()
            ->all();
    }

    private function mapSale(Sale $sale): array
    {
        return [
            'id' => $sale->id,
            'folio' => $sale->folio ?? ('V-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)),
            'date' => $sale->date?->format('d/m/Y H:i'),
            'user' => $this->cashierName($sale),
            'payment_method' => $sale->paymentMethod?->name ?? 'Sin metodo',
            'cash_box_number' => $sale->cash_box_number ?: '1',
            'total' => (float) $sale->total,
            'cash_received' => (float) ($sale->cash_received ?? 0),
            'change_due' => (float) ($sale->change_due ?? 0),
        ];
    }

    private function mapBranchSelector(Branch $branch): array
    {
        $current = $this->buildCurrentCut($branch);

        return array_merge($this->mapBranch($branch), [
            'sales_count' => $current['sales_count'],
            'sales_total' => $current['sales_total'],
            'expected_cash' => $current['expected_cash'],
            'card_total' => $current['card_total'],
            'other_total' => $current['other_total'],
            'active_users' => $current['active_users'],
            'has_activity' => $current['sales_count'] > 0,
        ]);
    }

    private function mapCashClosureReportBranch(Branch $branch): array
    {
        $summary = CashRegisterClosure::query()
            ->where('branch_id', $branch->id)
            ->selectRaw('COUNT(*) as cuts_count')
            ->selectRaw('COALESCE(SUM(sales_count), 0) as sales_count')
            ->selectRaw('COALESCE(SUM(sales_total), 0) as sales_total')
            ->selectRaw('COALESCE(SUM(expected_cash), 0) as expected_cash')
            ->selectRaw('COALESCE(SUM(card_total), 0) as card_total')
            ->selectRaw('COALESCE(SUM(cash_difference), 0) as cash_difference')
            ->selectRaw('COALESCE(SUM(card_difference), 0) as card_difference')
            ->first();

        $lastClosure = CashRegisterClosure::query()
            ->where('branch_id', $branch->id)
            ->latest('period_end')
            ->first();

        return array_merge($this->mapBranch($branch), [
            'cuts_count' => (int) ($summary->cuts_count ?? 0),
            'sales_count' => (int) ($summary->sales_count ?? 0),
            'sales_total' => (float) ($summary->sales_total ?? 0),
            'expected_cash' => (float) ($summary->expected_cash ?? 0),
            'card_total' => (float) ($summary->card_total ?? 0),
            'cash_difference' => (float) ($summary->cash_difference ?? 0),
            'card_difference' => (float) ($summary->card_difference ?? 0),
            'last_closure_at' => $lastClosure?->period_end?->format('d/m/Y H:i'),
            'last_closure_folio' => $lastClosure?->folio,
            'has_closures' => (int) ($summary->cuts_count ?? 0) > 0,
        ]);
    }

    private function mapBranch(Branch $branch): array
    {
        return [
            'id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
            'color' => $branch->color,
        ];
    }

    private function mapClosure(CashRegisterClosure $closure): array
    {
        return [
            'id' => $closure->id,
            'folio' => $closure->folio,
            'branch' => $closure->branch?->name ?? 'Sin sucursal',
            'user' => $closure->user?->name ?? 'Sin usuario',
            'cash_box_number' => $closure->cash_box_number,
            'period' => $closure->period_start?->format('d/m/Y H:i') . ' - ' . $closure->period_end?->format('d/m/Y H:i'),
            'sales_count' => $closure->sales_count,
            'sales_total' => (float) $closure->sales_total,
            'expected_cash' => (float) $closure->expected_cash,
            'card_total' => (float) $closure->card_total,
            'other_total' => (float) $closure->other_total,
            'recharge_total' => (float) $closure->recharge_total,
            'expected_drawer_cash' => (float) $closure->expected_drawer_cash,
            'counted_cash' => (float) $closure->counted_cash,
            'cash_left' => (float) $closure->cash_left,
            'counted_card' => (float) $closure->counted_card,
            'cash_difference' => (float) $closure->cash_difference,
            'card_difference' => (float) $closure->card_difference,
            'status' => abs((float) $closure->cash_difference) < 0.01 && abs((float) $closure->card_difference) < 0.01 ? 'Cuadrado' : 'Diferencia',
            'notes' => $closure->notes,
        ];
    }

    private function mapClosureSummary(?object $summary): array
    {
        return [
            'cuts_count' => (int) ($summary->cuts_count ?? 0),
            'sales_count' => (int) ($summary->sales_count ?? 0),
            'sales_total' => (float) ($summary->sales_total ?? 0),
            'expected_cash' => (float) ($summary->expected_cash ?? 0),
            'card_total' => (float) ($summary->card_total ?? 0),
            'other_total' => (float) ($summary->other_total ?? 0),
            'recharge_total' => (float) ($summary->recharge_total ?? 0),
            'expected_drawer_cash' => (float) ($summary->expected_drawer_cash ?? 0),
            'counted_cash' => (float) ($summary->counted_cash ?? 0),
            'cash_difference' => (float) ($summary->cash_difference ?? 0),
        ];
    }

    private function emptyClosureSummary(): array
    {
        return $this->mapClosureSummary(null);
    }

    private function emptyClosurePaginator(): array
    {
        return [
            'data' => [],
            'links' => [],
            'current_page' => 1,
            'from' => null,
            'last_page' => 1,
            'per_page' => 15,
            'to' => null,
            'total' => 0,
        ];
    }

    private function buildClosurePrintJobs(CashRegisterClosure $closure): array
    {
        $base = [
            'folio' => $closure->folio,
            'date' => $closure->period_end?->format('d/m/Y H:i'),
            'branch_name' => $closure->branch?->name ?? 'Sin sucursal',
            'user_name' => $closure->user?->name ?? 'Sin usuario',
            'cash_box_text' => 'CAJA #' . ($closure->cash_box_number ?: '1'),
            'cash_box_number' => $closure->cash_box_number ?: '1',
            'period_start' => $closure->period_start?->format('d/m/Y H:i'),
            'period_end' => $closure->period_end?->format('d/m/Y H:i'),
            'sales_count' => (int) $closure->sales_count,
            'sales_total' => (float) $closure->sales_total,
            'expected_cash' => (float) $closure->expected_cash,
            'card_total' => (float) $closure->card_total,
            'other_total' => (float) $closure->other_total,
            'expected_drawer_cash' => (float) $closure->expected_drawer_cash,
            'counted_cash' => (float) $closure->counted_cash,
            'cash_left' => (float) $closure->cash_left,
            'cash_withdrawal' => max(0, (float) $closure->counted_cash - (float) $closure->cash_left),
            'counted_card' => (float) $closure->counted_card,
            'cash_difference' => (float) $closure->cash_difference,
            'card_difference' => (float) $closure->card_difference,
            'denomination_breakdown' => $closure->denomination_breakdown ?? [],
            'payment_breakdown' => $closure->payment_breakdown ?? [],
            'notes' => $closure->notes,
        ];

        $jobs = [
            array_merge($base, [
                'type' => 'global',
                'title' => 'CORTE DE CAJA',
            ]),
        ];

        return $jobs;
    }

    private function normalizeDenominations(array $denominations): array
    {
        $valid = ['1000', '500', '200', '100', '50', '20b', '20m', '10', '5', '2', '1', '0.5'];

        return collect($valid)
            ->mapWithKeys(fn (string $key) => [$key => max(0, (int) ($denominations[$key] ?? 0))])
            ->all();
    }

    private function sumDenominations(array $denominations): float
    {
        $values = [
            '1000' => 1000,
            '500' => 500,
            '200' => 200,
            '100' => 100,
            '50' => 50,
            '20b' => 20,
            '20m' => 20,
            '10' => 10,
            '5' => 5,
            '2' => 2,
            '1' => 1,
            '0.5' => 0.5,
        ];

        $total = 0;

        foreach ($denominations as $key => $quantity) {
            $total += ($values[$key] ?? 0) * (int) $quantity;
        }

        return round($total, 2);
    }

    private function resolveBranchForCut(Request $request, Collection $branches): Branch
    {
        $branchId = $request->query('branch') ?: $branches->first()?->id;
        abort_unless($branchId, 404, 'No hay sucursales disponibles.');

        $branch = Branch::query()
            ->whereKey($branchId)
            ->where('active', true)
            ->firstOrFail();

        $this->abortIfUserCannotAccessBranch($request, $branch);

        return $branch;
    }

    private function cashClosureTicketTemplate(): array
    {
        $template = TicketTemplate::cashClosureTemplate();

        return [
            'id' => $template->id,
            'name' => $template->name,
            'slug' => $template->slug,
            'settings' => TicketTemplate::sanitizeSettings($template->settings ?? []),
        ];
    }

    private function accessibleBranches(Request $request): Collection
    {
        return $request->user()
            ->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name', 'branches.slug', 'branches.color')
            ->orderBy('branches.name')
            ->get();
    }

    private function cashierName(Sale $sale): string
    {
        $employee = $sale->employee;

        return $employee?->user?->name
            ?: trim(($employee?->first_name ?? '') . ' ' . ($employee?->last_name ?? ''))
            ?: 'Sin usuario';
    }

    private function isCashMethod(string $method): bool
    {
        return $this->paymentType($method) === 'cash';
    }

    private function paymentType(string $method): string
    {
        $method = mb_strtolower($method);

        if (str_contains($method, 'efectivo') || str_contains($method, 'cash')) {
            return 'cash';
        }

        if (
            str_contains($method, 'tarjeta') ||
            str_contains($method, 'card') ||
            str_contains($method, 'credito') ||
            str_contains($method, 'credito') ||
            str_contains($method, 'debito') ||
            str_contains($method, 'debito')
        ) {
            return 'card';
        }

        return 'cash';
    }
}
