<?php

declare(strict_types=1);

namespace App\Http\Middleware\Logging;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RequestInfo
{
    private array $ignoredPaths = [
        'horizon',
        'nova',
        'telescope'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        /** @var  Response */
        $response = $next($request);
        $ip = $request->server->get('REMOTE_ADDR');
        $path = $request->getPathInfo();
        $route = $request->route();
        $code = $response->getStatusCode();
        $forwardedHeader = $request->header('X-Forwarded-For');

        if ($forwardedHeader !== null) {
            $ip = array_map('trim', explode(',', $forwardedHeader))[0];
        }

        if ($route !== null) {
            $path = $request->route()->uri();
        }

        if ($route !== null && $this->isIgnoredPath($path)) {
            return $response;
        }

        $responseTime = microtime(true) - LARAVEL_START;

        dispatch(fn () => Log::info('HTTP Request', [
            'ip' => $ip,
            'path' => $path,
            'response_code' => $code,
            'response_time' => $responseTime
        ]));

        return $response;
    }

    private function isIgnoredPath($path)
    {
        foreach ($this->ignoredPaths as $ignoredPath) {
            if (preg_match("#^(\/)?{$ignoredPath}#", $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
