<?php

namespace App\Http\Middleware;

use Closure;

class GaeProxyIp
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
        if (isset($_SERVER['GAE_SERVICE'])) {
            $forwardedFor = array_map('trim', explode(',', $request->header('X-Forwarded-For')));
            $request->server->set('REMOTE_ADDR', $_SERVER['REMOTE_ADDR'] = $forwardedFor[0]);
        }

        return $next($request);
    }
}
