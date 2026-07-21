<?php

namespace App\Http\Controllers;

use App\Models\SystemAudit;
use App\Support\SystemPermission;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SystemAuditController extends SystemAdministrationController
{
    public function index(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::AUDIT_VIEW);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer'],
            'result' => ['nullable', 'in:success,error'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'perPage' => ['nullable', 'integer'],
        ]);

        $audits = SystemAudit::query()
            ->with('user:id,name,email')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('user_name', 'like', "%{$search}%")
                    ->orWhere('record_label', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            }))
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['result'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('occurred_at', '<=', $to))
            ->latest('occurred_at')
            ->paginate(TablePagination::resolvePerPage($request, 50))
            ->withQueryString();

        return Inertia::render('SystemAdministration/Audits', [
            'audits' => $audits,
            'filters' => $filters,
            'modules' => SystemAudit::query()->distinct()->orderBy('module')->pluck('module')->values(),
        ]);
    }

    public function export(Request $request)
    {
        $this->authorizePermission($request, SystemPermission::AUDIT_EXPORT);
        $this->audit()->record('system-audit', 'export', 'success', $request);

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Fecha', 'Usuario', 'Rol', 'Módulo', 'Acción', 'Registro', 'Resultado', 'IP']);

            SystemAudit::query()->latest('occurred_at')->cursor()->each(function (SystemAudit $audit) use ($output) {
                fputcsv($output, [
                    $audit->occurred_at?->toDateTimeString(), $audit->user_name, $audit->role_name,
                    $audit->module, $audit->action, $audit->record_label, $audit->result, $audit->ip_address,
                ]);
            });

            fclose($output);
        }, 'auditoria-del-sistema-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function audit(): \App\Services\SystemAuditService
    {
        return app(\App\Services\SystemAuditService::class);
    }
}
