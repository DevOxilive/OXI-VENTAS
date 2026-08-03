<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $fingerprint = $request->header('Idempotency-Key') ?: hash('sha256', json_encode([
            'user_id' => $request->user()?->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'payload' => $request->except(['_token', '_method']),
            'files' => collect($request->allFiles())->map(function ($file) {
                if (is_array($file)) {
                    return collect($file)->map(fn ($item) => [
                        'name' => $item->getClientOriginalName(),
                        'size' => $item->getSize(),
                        'hash' => hash_file('sha256', $item->getRealPath()),
                    ])->all();
                }

                return [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'hash' => hash_file('sha256', $file->getRealPath()),
                ];
            })->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $completedKey = "duplicate-submission:completed:{$fingerprint}";
        $lock = Cache::lock("duplicate-submission:lock:{$fingerprint}", 15);

        if (Cache::has($completedKey) || ! $lock->get()) {
            $message = 'Esta operacion ya fue enviada. Espera la actualizacion antes de intentarlo nuevamente.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 409)
                : back()->withErrors(['duplicate_submission' => $message]);
        }

        try {
            $response = $next($request);

            $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');

            if ($response->getStatusCode() < 400 && !$hasValidationErrors) {
                Cache::put($completedKey, true, now()->addSeconds(10));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
