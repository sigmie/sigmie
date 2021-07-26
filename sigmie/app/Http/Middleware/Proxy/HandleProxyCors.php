<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Services\ProxyCorsService;
use Fruitcake\Cors\HandleCors as CorsHandleCors;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

class HandleProxyCors extends CorsHandleCors
{
    public function __construct(
        ProxyCorsService $cors,
        Container $container
    ) {
        $this->cors = $cors;
        $this->container = $container;
    }

    public function shouldRun(Request $request): bool
    {
        return true;
    }
}
