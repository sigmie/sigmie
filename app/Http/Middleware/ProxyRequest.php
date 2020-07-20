<?php

namespace App\Http\Middleware;

use Closure;

class ProxyRequest
{
    /**
     * Because of the limitation on the laravel sectum the
     * function $request->user(); has to be user in order
     * to retrieve the Cluster which is confusing. To avoid
     * this confusion in the controller we retrieve the cluster
     * and pass is a route argument to the controller action which
     * has the benefit of directly injecting the Cluster
     * instance to the controller.
     */
    public function handle($request, Closure $next)
    {
        $cluster =  $request->user();

        $request->setUserResolver(fn () => null);

        $request->route()->setParameter('cluster', $cluster);

        return $next($request);
    }
}
