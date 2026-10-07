<?php

namespace App\Http\Controllers;

use App\Models\SystemAudit;
use App\Support\SystemPermission;
use App\Support\TablePagination;
use App\Support\LocalDateTime;
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
            'per_page' => ['nullable', 'integer'],
        ]);

        $audits = SystemAudit::query()
            ->select([
                'id',
                'user_name',
                'role_name',
                'module',
                'action',
                'record_label',
                'result',
                'occurred_at',
            ])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(function ($inner) use ($search) {
                $inner->where('user_name', 'like', "%{$search}%")
                    ->orWhere('record_label', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('module', 'like', "%{$search}%");
            }))
            ->when($filters['module'] ?? null, fn ($query, $module) => $query->where('module', $module))
            ->when($filters['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filters['result'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('occurred_at', '>=', LocalDateTime::startOfDay($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('occurred_at', '<=', LocalDateTime::endOfDay($to)))
            ->latest('occurred_at')
            ->paginate(TablePagination::resolvePerPage($request))
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
                    LocalDateTime::format($audit->occurred_at, 'Y-m-d H:i:s'), $audit->user_name, $audit->role_name,
                    $audit->module, $audit->action, $audit->record_label, $audit->result, $audit->ip_address,
                ]);
            });

            fclose($output);
        }, 'auditoria-del-sistema-' . LocalDateTime::now('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function audit(): \App\Services\SystemAuditService
    {
        return app(\App\Services\SystemAuditService::class);
    }
}
