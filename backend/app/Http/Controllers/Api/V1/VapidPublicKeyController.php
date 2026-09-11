<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Serves the VAPID public key so browsers can subscribe. The private key
 * never leaves the server, so only the public half is exposed here.
 */
class VapidPublicKeyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $publicKey = config('vapid.public_key');

        if ($publicKey === null || $publicKey === '') {
            return response()->json([
                'error' => 'VAPID public key is not configured. Run `php artisan push:vapid-keys`.',
            ], 503);
        }

        return response()->json(['data' => ['public_key' => $publicKey]]);
    }
}
