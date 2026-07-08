<?php

return [
    'certificate_path' => env('QZ_CERTIFICATE_PATH', storage_path('app/qz/qz-public.pem')),
    'private_key_path' => env('QZ_PRIVATE_KEY_PATH', storage_path('app/qz/qz-private.pem')),
    'private_key_passphrase' => env('QZ_PRIVATE_KEY_PASSPHRASE'),
];
