<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Proxy Route
|--------------------------------------------------------------------------
|
| The proxy route is available on a different domain from the app.
| If the proxy domain is called the invocable proxy controller
| is dispatched.
|
*/

use App\Http\Controllers\Proxy\ProxyController;

Route::any('/{endpoint?}', ProxyController::class)
    ->where('endpoint', '.*')
    ->name('proxy')
    ->middleware([
        \App\Http\Middleware\Proxy\FormatUnauthenticatedResponse::class,
        \App\Http\Middleware\Proxy\HandleProxyCors::class,
        'auth:sanctum',
        \App\Http\Middleware\Proxy\ProxyRequest::class,
        \App\Http\Middleware\Proxy\VerifyClusterState::class,
        \App\Http\Middleware\Proxy\VerifyTokenStatus::class,
        \App\Http\Middleware\Proxy\VerifyTokenPermissions::class,
        \App\Http\Middleware\Proxy\SaveRequestStats::class,
    ]);
