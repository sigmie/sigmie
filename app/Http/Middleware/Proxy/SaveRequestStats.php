<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Helpers\ProxyRequestResponse;
use App\Jobs\Proxy\SaveProxyRequest;
use Closure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SaveRequestStats
{
    public function __construct(private ProxyRequest $proxyRequest)
    {
    }

    public function handle($request, Closure $next)
    {
        return $next($request);
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    public function terminate($request, $response)
    {
        $data = new ProxyRequestResponse($request, $response);

        if ($this->isUnauthorizedResponse($response)) {
            return;
        }

        dispatch(
            new SaveProxyRequest(
                $data(),
                $this->proxyRequest->cluster()->id
            )
        );
    }

    private function isUnauthorizedResponse(Response $response): bool
    {
        return $response->getStatusCode() === 401;
    }
}
