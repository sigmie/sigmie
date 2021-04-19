<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Models\AbstractCluster;
use App\Models\Cluster;
use Closure;
use Exception;

class VerifyClusterState
{
    protected AbstractCluster $cluster;

    protected ProxyRequest $proxyRequest;

    public function __construct(ProxyRequest $proxyRequest)
    {
        $this->proxyRequest = $proxyRequest;
    }

    public function handle($request, Closure $next)
    {
        $code = 400; // https://stackoverflow.com/questions/39129275/http-status-code-to-send-if-server-state-invalid

        if ($this->clusterIsRunning()) {
            return $next($request);
        }

        if ($this->clusterIsCreating()) {
            return response()->json(['error' => $code, 'status' => 'Cluster not ready.'], $code);
        }

        if ($this->clusterIsDestroyed()) {
            return response()->json(['error' => $code, 'status' => 'Cluster destroyed.'], $code);
        }

        if ($this->clusterHasFailed()) {
            return response()->json(['error' => $code, 'status' => 'Cluster failed.'], $code);
        }

        throw new Exception('Cluster has an invalid state.');
    }

    public function clusterHasFailed()
    {
        $state = $this->proxyRequest->cluster()->getAttribute('state');

        return $state === Cluster::FAILED;
    }


    public function clusterIsDestroyed()
    {
        $state = $this->proxyRequest->cluster()->getAttribute('state');

        return in_array($state, [Cluster::QUEUED_DESTROY, Cluster::DESTROYED]);
    }

    private function clusterIsCreating()
    {
        $state = $this->proxyRequest->cluster()->getAttribute('state');

        return in_array($state, [Cluster::QUEUED_CREATE, Cluster::CREATED]);
    }

    private function clusterIsRunning()
    {
        return $this->proxyRequest->cluster()->getAttribute('state') === Cluster::RUNNING;
    }
}
