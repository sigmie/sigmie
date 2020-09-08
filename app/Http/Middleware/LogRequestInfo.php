<?php

namespace App\Http\Middleware;

use Amp\Parallel\Worker\TaskFailureException;
use Closure;
use Exception;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class LogRequestInfo
{
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

        Log::info('HTTP Request', [
            'ip' => $request->getClientIp(),
            'path' => $request->getPathInfo(),
            'response_code' => $response->getStatusCode(),
            'age' => $response->getAge(),
        ]);

        return $response;
    }
}
