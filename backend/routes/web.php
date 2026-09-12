<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Single-origin serving
|--------------------------------------------------------------------------
|
| The Build Angular app (see `composer frontend:build`) is served by Laravel
| at the root so the UI, the /api/* endpoints, and web push all share one
| origin. This catch-all returns the SPA's index.html for any GET that is
| not a Laravel-owned route: /api/* (routes/api.php) and the /up health
| probe keep precedence, everything else is an SPA deep link.
|
*/

Route::get('/{path?}', function () {
    $frontend = public_path('index.html');

    if (! is_file($frontend)) {
        abort(503, 'The Angular frontend has not been built. Run: composer frontend:build');
    }

    return response(File::get($frontend))->header('Content-Type', 'text/html; charset=UTF-8');
})->where('path', '^(?!api(?=/|$)|up(?=/|$)|storage(?=/)).*$');