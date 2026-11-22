<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PreventDuplicateSaleOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');
        abort_unless($request->user(), 401);

        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $key)) {
            return response()->json(['message' => 'No se pudo identificar la venta. Cierra el cobro e inténtalo nuevamente.'], 422);
        }

        $keyHash = hash('sha256', json_encode([
            $request->user()->id, $request->method(), $request->route()->getName(), $request->path(), $key,
        ], JSON_THROW_ON_ERROR));
        $payloadHash = hash('sha256', json_encode(
            $this->normalizePayload($request->except(['_token', '_method'])),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ));
        $committed = false;
        $response = null;

        try {
            return DB::transaction(function () use ($request, $next, $keyHash, $payloadHash, &$committed, &$response) {
                // Esta bandera diferencia un fallo antes de guardar de un aviso que falló después de confirmar.
                DB::afterCommit(function () use (&$committed): void {
                    $committed = true;
                });

                $now = now();
                DB::table('sale_operations')->insertOrIgnore([
                    'user_id' => $request->user()->id,
                    'route_name' => $request->route()->getName(),
                    'key_hash' => $keyHash,
                    'payload_hash' => $payloadHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $operation = DB::table('sale_operations')->where('key_hash', $keyHash)
                    ->lockForUpdate()->firstOrFail();

                if (! hash_equals($operation->payload_hash, $payloadHash)) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Este cobro ya fue enviado con otros datos. Para proteger la venta, revisa el ticket antes de iniciar un cobro nuevo.',
                    ], 409));
                }

                if ($operation->response_status !== null) {
                    $response = new Response(
                        $operation->response_body ?? '',
                        $operation->response_status,
                        json_decode($operation->response_headers ?? '{}', true, 512, JSON_THROW_ON_ERROR),
                    );
                    $response->headers->set('Idempotency-Replayed', 'true');

                    return $response;
                }

                $response = $next($request);

                if ($response->getStatusCode() >= 400) {
                    // Un error de validación no debe bloquear la corrección y el nuevo intento.
                    throw new HttpResponseException($response);
                }

                $headers = [];
                foreach (['Content-Type', 'Location', 'X-Inertia-Location'] as $header) {
                    if ($response->headers->has($header)) {
                        $headers[$header] = $response->headers->get($header);
                    }
                }

                DB::table('sale_operations')->where('id', $operation->id)->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'response_headers' => json_encode($headers, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                return $response;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($committed && $response instanceof Response) {
                report($exception);

                return $response;
            }

            if ($exception instanceof HttpResponseException) {
                return $exception->getResponse();
            }

            throw $exception;
        }
    }

    private function normalizePayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->normalizePayload($item), $value);
    }
}
