<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Helpers\ProxyRequestResponse;
use App\Jobs\Proxy\SaveProxyRequest;
use Closure;

class SaveRequestStats
{
    public function __construct(private ProxyRequest $proxyRequest)
    {
    }
    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    public function terminate($request, $response)
    {
        $data = new ProxyRequestResponse($request, $response);

        // dispatch(
        //     new SaveProxyRequest(
        //         $data(),
        //         $this->proxyRequest->cluster()->id
        //     )
        // );
    }
}
