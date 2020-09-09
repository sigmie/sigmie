<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogRequestInfo
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
     * @param  \Closure  $next
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

        if (isset($_SERVER['GAE_SERVICE'])) {
            $forwardedFor = array_map('trim', explode(',', $request->header('X-Forwarded-For')));
            $request->server->set('REMOTE_ADDR', $_SERVER['REMOTE_ADDR'] = $forwardedFor[0]);
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
