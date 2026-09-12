<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Single-origin serving
|--------------------------------------------------------------------------
|
| The built Angular app (see `composer frontend:build`) is served by Laravel
| at the root so the UI, the /api/* endpoints, and web push all share one
| origin. Route::fallback() runs only when no other route matched — the
| /api/v1/* routes and the /up health probe keep precedence by construction,
| and every other GET is a client-side SPA deep link served index.html.
|
*/

Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        abort(404);
    }

    $frontend = public_path('index.html');

    if (! is_file($frontend)) {
        abort(503, 'The Angular frontend has not been built. Run: composer frontend:build');
    }

    return response(File::get($frontend))->header('Content-Type', 'text/html; charset=UTF-8');
});
