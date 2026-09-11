<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID (web push) keys
    |--------------------------------------------------------------------------
    |
    | The ECDSA P-256 keypair used to authenticate web-push delivery. The
    | private key signs each push request; the public key is served to the
    | browser as the push `applicationServerKey`. Values are url-safe base64,
    | generated with `php artisan push:vapid-keys`. NEVER commit real keys.
    |
    */
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
];
