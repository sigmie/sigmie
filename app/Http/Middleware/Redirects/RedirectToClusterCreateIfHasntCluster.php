<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use Closure;

class RedirectToClusterCreateIfHasntCluster
{
    public function handle($request, Closure $next)
    {
        $project = $request->route('project');
        $clusters = $project->clusters()->get();

        if ($clusters->isEmpty()) {
            return redirect()->route('cluster.create');
        }

        return $next($request);
    }
}
