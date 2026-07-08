<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QzTrayController extends Controller
{
    public function certificate(): Response
    {
        $certificatePath = config('qz.certificate_path');

        abort_unless(is_string($certificatePath) && is_file($certificatePath), 404, 'Certificado de QZ no configurado.');

        return response(file_get_contents($certificatePath), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function sign(Request $request): Response
    {
        $data = $request->validate([
            'data' => ['required', 'string'],
        ])['data'];

        $privateKeyPath = config('qz.private_key_path');
        abort_unless(is_string($privateKeyPath) && is_file($privateKeyPath), 500, 'Llave privada de QZ no configurada.');

        $privateKey = openssl_pkey_get_private(
            file_get_contents($privateKeyPath),
            config('qz.private_key_passphrase') ?: ''
        );

        abort_unless($privateKey, 500, 'No se pudo leer la llave privada de QZ.');

        $signed = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_pkey_free($privateKey);

        abort_unless($signed, 500, 'No se pudo firmar la solicitud de QZ.');

        return response(base64_encode($signature), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
