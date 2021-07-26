<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use Closure;
use Symfony\Component\HttpFoundation\Response;

class ProfileResponse
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /** @var Response */
        $response = $next($request);

        if (app()->bound('debugbar') &&
            app('debugbar')->isEnabled()
        ) {
            $response->setContent(json_encode(app('debugbar')->getData()));
        }

        return $response;
    }
}
