<?php

namespace App\Http\Controllers;

use App\Services\SystemAuditService;
use App\Services\SystemRecoveryService;
use App\Services\SystemTrashPurgeService;
use App\Support\SystemPermission;
use App\Support\TablePagination;
use App\Support\TrashRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class SystemTrashController extends SystemAdministrationController
{
    public function __construct(
        private readonly SystemAuditService $audit,
        private readonly SystemRecoveryService $recovery,
        private readonly SystemTrashPurgeService $purgeService,
    ) {}

    public function index(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_VIEW);
        $resource = $request->string('resource')->toString() ?: 'all';
        $search = $request->string('search')->toString();
        $period = $request->string('period')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        try {
            $records = $resource === 'all'
                ? $this->allRecords($request, $search, $period, $from, $to)
                : $this->resourceRecords($request, $resource, $search, $period, $from, $to);
        } catch (\Throwable) {
            abort(404);
        }

        return Inertia::render('SystemAdministration/Trash', [
            'resources' => TrashRegistry::resources(),
            'resource' => $resource,
            'records' => $records,
            'filters' => [
                'search' => $search,
                'period' => $period,
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
            ],
        ]);
    }

    public function restore(Request $request, string $resource, int $record)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_RESTORE);
        $this->recovery->restoreCompletely($resource, $record, $request);

        return back()->with('success', 'Registro restaurado correctamente.');
    }

    public function restoreMany(Request $request, string $resource)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_RESTORE);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
        $this->recovery->restoreMany($resource, $ids, $request);

        return back()->with('success', 'Registros restaurados correctamente.');
    }

    public function restoreAll(Request $request, string $resource)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_RESTORE);
        $this->recovery->restoreAll($resource, $request);

        return back()->with('success', 'Todos los registros del módulo fueron restaurados.');
    }

    public function forceDelete(Request $request, string $resource, int $record)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_FORCE_DELETE);
        $this->confirm($request);
        $model = TrashRegistry::find($resource, $record);
        $label = TrashRegistry::recordLabel($model);
        $this->purgeService->purge($model);
        $this->audit->record('system-trash', 'force_delete', 'success', $request, [
            'record_type' => $model::class, 'record_id' => $record, 'record_label' => $label,
        ]);

        return back()->with('success', 'Registro eliminado permanentemente.');
    }

    public function empty(Request $request, string $resource)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_EMPTY);
        $this->confirm($request);
        $models = TrashRegistry::query($resource)->get();
        $models->each(fn ($model) => $this->purgeService->purge($model));
        $this->audit->record('system-trash', 'empty', 'success', $request, ['metadata' => ['resource' => $resource]]);

        return back()->with('success', 'Papelera vaciada correctamente.');
    }

    public function purgeExpired(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::TRASH_EMPTY);
        $this->confirm($request);
        $summary = $this->purgeService->purgeExpired($request);

        return back()->with('success', array_sum($summary) . ' registros vencidos fueron depurados.');
    }

    private function confirm(Request $request): void
    {
        $request->validate(['confirmation' => ['required', 'in:ELIMINAR']]);
    }

    private function resourceRecords(Request $request, string $resource, string $search, string $period, $from, $to): LengthAwarePaginator
    {
        return $this->applyFilters(TrashRegistry::query($resource), $resource, $search, $period, $from, $to)
            ->latest('deleted_at')
            ->paginate(TablePagination::resolvePerPage($request, 50))
            ->withQueryString()
            ->through(fn ($model) => $this->recordPayload($model, $resource));
    }

    private function allRecords(Request $request, string $search, string $period, $from, $to): LengthAwarePaginator
    {
        $all = collect(TrashRegistry::resources())
            ->flatMap(function (array $definition) use ($search, $period, $from, $to) {
                $resource = $definition['key'];

                return $this->applyFilters(TrashRegistry::query($resource), $resource, $search, $period, $from, $to)
                    ->get()
                    ->map(fn ($model) => $this->recordPayload($model, $resource));
            })
            ->sortByDesc('deleted_at')
            ->values();

        $perPage = TablePagination::resolvePerPage($request, 50);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return (new LengthAwarePaginator(
            $all->forPage($page, $perPage)->values(),
            $all->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        ));
    }

    private function applyFilters(Builder $query, string $resource, string $search, string $period, $from, $to): Builder
    {
        return $query
            ->when($period, function (Builder $query) use ($period, $resource) {
                match ($period) {
                    'today' => $query->whereDate('deleted_at', today()),
                    'last_7_days' => $query->whereBetween('deleted_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()]),
                    'last_30_days' => $query->whereBetween('deleted_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()]),
                    'last_90_days' => $query->whereBetween('deleted_at', [now()->subDays(89)->startOfDay(), now()->endOfDay()]),
                    'expired' => \App\Support\TrashRetentionPolicy::isPurgeable($resource)
                        ? $query->where('deleted_at', '<=', now()->subDays(\App\Support\TrashRetentionPolicy::days($resource)))
                        : $query->whereRaw('1 = 0'),
                    default => null,
                };
            })
            ->when($from, fn (Builder $query) => $query->where('deleted_at', '>=', $from->startOfDay()))
            ->when($to, fn (Builder $query) => $query->where('deleted_at', '<=', $to->endOfDay()))
            ->when($search, fn (Builder $query) => $query->where(function (Builder $inner) use ($search, $resource) {
                foreach (TrashRegistry::searchableColumns($resource) as $column) {
                    $inner->orWhere($column, 'like', "%{$search}%");
                }
            }));
    }

    private function recordPayload($model, string $resource): array
    {
        return [
            'id' => $model->getKey(),
            'resource' => $resource,
            'module' => TrashRegistry::label($resource),
            'label' => TrashRegistry::recordLabel($model),
            'deleted_at' => $model->deleted_at,
        ];
    }

}
