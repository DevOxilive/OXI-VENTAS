<?php

namespace App\Http\Middleware;

use App\Services\SystemAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditSystemActions
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->routeIs('system.audits.*')) {
            return $next($request);
        }

        try {
            $response = $next($request);
            $this->record($request, $response->getStatusCode() >= 400 ? 'error' : 'success');

            return $response;
        } catch (Throwable $exception) {
            $this->record($request, 'error', $exception->getMessage());
            throw $exception;
        }
    }

    private function record(Request $request, string $result, ?string $observations = null): void
    {
        $route = $request->route();
        $name = $route?->getName() ?? 'unidentified';
        $segments = explode('.', $name);
        $parameters = collect($route?->parameters() ?? []);
        $record = $parameters->first(fn ($value) => is_object($value) && method_exists($value, 'getKey'));

        if ($record) {
            return;
        }

        $this->audit->record(
            $segments[0] ?? 'system',
            $this->action($request, $name),
            $result,
            $request,
            [
                'record_type' => $record ? $record::class : null,
                'record_id' => $record?->getKey(),
                'record_label' => $record?->name ?? $record?->title ?? $record?->folio ?? null,
                'observations' => $observations,
                'metadata' => ['route' => $name],
            ],
        );
    }

    private function action(Request $request, string $routeName): string
    {
        if (str_contains($routeName, 'restore')) return 'restore';
        if (str_contains($routeName, 'force-delete')) return 'force_delete';
        if (str_contains($routeName, 'export')) return 'export';

        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => strtolower($request->method()),
        };
    }
}
