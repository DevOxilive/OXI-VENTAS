<?php

return [
    'relying_party_id' => env('PASSKEYS_RELYING_PARTY_ID', parse_url(config('app.url'), PHP_URL_HOST)),
    // Laragon serves this project on :8000 during local development. Keep the
    // configured application URL for deployments and the local origin for WebAuthn.
    'allowed_origins' => array_values(array_filter(array_unique([
        ...array_filter(explode(',', (string) env('PASSKEYS_ALLOWED_ORIGINS', ''))),
        config('app.url'),
        'http://localhost:8000',
    ]))),
    'user_handle_secret' => env('PASSKEYS_USER_HANDLE_SECRET', config('app.key')),
    'timeout' => 60000,
    'guard' => 'web',
    'middleware' => ['web'],
    'management_middleware' => [],
    'throttle' => 'throttle:6,1',
    'redirect' => '/',
];
