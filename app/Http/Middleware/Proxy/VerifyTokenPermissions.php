<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Http\Controllers\Cluster\TokenController;
use App\Models\AbstractCluster;
use Closure;

class VerifyTokenPermissions
{
    protected AbstractCluster $cluster;

    protected array $searchPathPatterns = [
        '@\/*\/_search@'
    ];

    protected ProxyRequest $proxyRequest;

    public function __construct(ProxyRequest $proxyRequest)
    {
        $this->proxyRequest = $proxyRequest;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function handle($request, Closure $next)
    {
        $this->cluster = $this->proxyRequest->cluster();

        if ($this->isAdminTokenRequest()) {
            return $next($request);
        }

        if ($this->isSearchPath($request->getPathInfo())) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized token type.'], 403);
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
}
