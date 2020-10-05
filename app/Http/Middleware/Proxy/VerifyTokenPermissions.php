<?php

namespace App\Http\Middleware\Proxy;

use App\Http\Controllers\Cluster\TokenController;
use App\Models\Cluster;
use Closure;

class VerifyTokenPermissions
{
    protected Cluster $cluster;

    protected array $searchPathPatterns = [
        '@\/*\/_search@'
    ];

    public function __construct(ProxyRequest $proxyRequest)
    {
        $this->cluster = $proxyRequest->cluster();
    }

    private function isAdminTokenRequest(): bool
    {
        return $this->cluster->currentAccessToken()->getAttribute('name') === TokenController::ADMIN;
    }

    private function isSearchPath(string $path)
    {
        foreach ($this->searchPathPatterns as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {
        if ($this->isAdminTokenRequest()) {
            return $next($request);
        }

        if ($this->isSearchPath($request->getPathInfo())) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized token type.'], 403);
    }
}
