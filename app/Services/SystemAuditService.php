<?php

namespace App\Services;

use App\Models\SystemAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SystemAuditService
{
    public function record(
        string $module,
        string $action,
        string $result = 'success',
        ?Request $request = null,
        array $context = [],
    ): void {
        if (!Schema::hasTable('system_audits')) {
            return;
        }

        $request ??= request();
        $user = $context['actor'] ?? $request?->user();
        $userAgent = (string) ($request?->userAgent() ?? '');

        try {
            SystemAudit::create([
                'user_id' => $user?->id,
                'user_name' => $context['actor_name'] ?? $user?->name,
                'role_name' => $context['role_name'] ?? $user?->role?->name,
                'module' => $module,
                'action' => $action,
                'auditable_type' => $context['record_type'] ?? null,
                'auditable_id' => $context['record_id'] ?? null,
                'record_label' => $context['record_label'] ?? null,
                'result' => $result,
                'observations' => $context['observations'] ?? null,
                'ip_address' => $request?->ip(),
                'user_agent' => $userAgent ?: null,
                'browser' => $this->browser($userAgent),
                'operating_system' => $this->operatingSystem($userAgent),
                'device' => $this->device($userAgent),
                'url' => $request?->fullUrl(),
                'method' => $request?->method(),
                'metadata' => $context['metadata'] ?? null,
                'occurred_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function browser(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Edg/') => 'Microsoft Edge',
            str_contains($agent, 'Chrome/') => 'Google Chrome',
            str_contains($agent, 'Firefox/') => 'Mozilla Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Desconocido',
        };
    }

    private function operatingSystem(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Desconocido',
        };
    }

    private function device(string $agent): string
    {
        return match (true) {
            preg_match('/Mobile|Android|iPhone/i', $agent) === 1 => 'Móvil',
            preg_match('/iPad|Tablet/i', $agent) === 1 => 'Tableta',
            default => 'Escritorio',
        };
    }
}
