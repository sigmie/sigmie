<?php declare(strict_types=1);

namespace App\Http\Middleware\Logging;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogProxyRequest
{
    private array $ignoredPaths = [];

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

        $path = $request->getPathInfo();
        $code = $response->getStatusCode();

        $responseTime = microtime(true) - LARAVEL_START;
        $ip = array_map('trim', explode(',', $request->header('X-Forwarded-For')))[0];

        dispatch(fn () => Log::info('Proxy Request', [
            'ip' => $ip,
            'path' => $path,
            'response_code' => $code,
            'response_time' => $responseTime
        ]));

        return $response;
    }
}
