<?php

namespace App\Http\Middleware\Proxy;

use App\Http\Controllers\Cluster\TokenController;
use App\Models\Cluster;
use Closure;

class VerifyTokenStatus
{
    protected Cluster $cluster;

    public function __construct(ProxyRequest $proxyRequest)
    {
        $this->cluster = $proxyRequest->cluster();
    }

    public function isSearchTokenRequest(): bool
    {
        return $this->cluster->currentAccessToken()->getAttribute('name') === TokenController::SEARCH_ONLY;
    }

    public function isAdminTokenRequest(): bool
    {
        return $this->cluster->currentAccessToken()->getAttribute('name') === TokenController::ADMIN;
    }

    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        if ($this->isAdminTokenRequest() && $this->cluster->isAdminTokenActive()) {
            return $next($request);
        }

        if ($this->isSearchTokenRequest() && $this->cluster->isSearchTokenActive()) {
            return $next($request);
        }


        return response()->json(['message' => 'Inactive token.'], 403);
    }
}
