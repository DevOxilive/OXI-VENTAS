<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PreventDuplicateInventoryOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key');
        abort_unless($request->user(), 401);

        if (! preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $key)) {
            return response()->json(['message' => 'No se pudo identificar la operación. Vuelve a abrir el formulario.'], 422);
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
                $committed = false;
                $response = null;

                // Laravel procesa callbacks de transacciones hijas antes de los de la raíz.
                // Este primer savepoint deja la bandera antes de cualquier callback del movimiento.
                DB::transaction(function () use (&$committed) {
                    DB::afterCommit(function () use (&$committed) {
                        $committed = true;
                    });
                });

                $now = now();
                DB::table('inventory_operations')->insertOrIgnore([
                    'user_id' => $request->user()->id,
                    'route_name' => $request->route()->getName(),
                    'key_hash' => $keyHash,
                    'payload_hash' => $payloadHash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $operation = DB::table('inventory_operations')->where('key_hash', $keyHash)
                    ->lockForUpdate()->firstOrFail();

                if (! hash_equals($operation->payload_hash, $payloadHash)) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'Esta captura ya fue registrada con otros datos. Cierra el formulario y vuelve a abrirlo antes de registrar otra operación.',
                    ], 409));
                }

                if ($operation->response_status !== null) {
                    foreach (json_decode($operation->flash_payload ?? '{}', true, 512, JSON_THROW_ON_ERROR) as $name => $value) {
                        if ($request->hasSession()) {
                            $request->session()->flash($name, $value);
                        }
                    }

                    $response = new Response(
                        $operation->response_body ?? '',
                        $operation->response_status,
                        json_decode($operation->response_headers ?? '{}', true, 512, JSON_THROW_ON_ERROR),
                    );
                    $response->headers->set('Idempotency-Replayed', 'true');

                    return $response;
                }

                $response = $next($request);
                $hasNewErrors = $request->hasSession()
                    && in_array('errors', $request->session()->get('_flash.new', []), true);

                if ($response->getStatusCode() >= 400 || $hasNewErrors) {
                    // También revierte cualquier escritura que el controlador hiciera antes del error.
                    throw new HttpResponseException($response);
                }

                $headers = [];
                foreach (['Content-Type', 'Location', 'X-Inertia-Location'] as $header) {
                    if ($response->headers->has($header)) {
                        $headers[$header] = $response->headers->get($header);
                    }
                }
                $flash = $request->hasSession()
                    ? $request->session()->only(array_intersect(
                        ['success', 'movement_id'], $request->session()->get('_flash.new', []),
                    ))
                    : [];

                DB::table('inventory_operations')->where('id', $operation->id)->update([
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'response_headers' => json_encode($headers, JSON_THROW_ON_ERROR),
                    'flash_payload' => json_encode($flash, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);

                return $response;
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($committed && $response instanceof Response) {
                // Un aviso fallido después de confirmar no debe hacer repetir el inventario.
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
