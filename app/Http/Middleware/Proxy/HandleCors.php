<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use Fruitcake\Cors\HandleCors as CorsHandleCors;
use Closure;
use Asm89\Stack\CorsService;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Symfony\Component\HttpFoundation\Response;

class HandleCors extends CorsHandleCors
{
    /**
     * Handle an incoming request. Based on Asm89\Stack\Cors by asm89
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        // Check if we're dealing with CORS and if we should handle it
        if (!$this->shouldRun($request)) {
            return $next($request);
        }

        // For Preflight, return the Preflight response
        if ($this->cors->isPreflightRequest($request)) {
            return $this->cors->handlePreflightRequest($request);
        }

        // If the request is not allowed, return 403
        if (!$this->cors->isActualRequestAllowed($request)) {
            return new Response('Not allowed in CORS policy.', 403);
        }

        // Add the headers on the Request Handled event as fallback in case of exceptions
        if (class_exists(RequestHandled::class) && $this->container->bound('events')) {
            $this->container->make('events')->listen(RequestHandled::class, function (RequestHandled $event) {
                $this->addHeaders($event->request, $event->response);
            });
        }

        // Handle the request
        $response = $next($request);

        // Add the CORS headers to the Response
        return $this->addHeaders($request, $response);
    }

    /**
     * The the path from the config, to see if the CORS Service should run
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isMatchingPath(Request $request): bool
    {
        return true;
    }
}
