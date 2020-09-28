<?php

declare(strict_types=1);

namespace App\Http\Middleware\Proxy;

use App\Models\Cluster;
use Closure;
use Illuminate\Http\Request;

class ProxyRequest
{
    private Cluster $cluster;

    /**
     * Because of the limitation on the laravel sectum the
     * function $request->user(); has to be user in order
     * to retrieve the Cluster which is confusing. To avoid
     * this confusion in the controller we retrieve the cluster
     * and pass is a route argument to the controller action which
     * has the benefit of directly injecting the Cluster
     * instance to the controller.
     *
     * @param Request $request
     */
    public function handle($request, Closure $next)
    {
        $this->cluster = $request->user();

        $request->setUserResolver(fn () => null);

        return $next($request);
    }

    public function cluster()
    {
        return $this->cluster;
    }
}
