<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Http\Controllers\Cluster\TokenController;
use App\Jobs\Proxy\SaveProxyRequest;
use App\Models\Cluster;
use Closure;

class SaveRequestStats
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        dispatch(new SaveProxyRequest($response, $request))->afterResponse();

        return $response;
    }
}
