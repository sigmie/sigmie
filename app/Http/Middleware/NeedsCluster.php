<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;

class NeedsCluster
{
    public function handle($request, Closure $next)
    {
        $project = $request->route('project');
        $clusters = $project->getAttribute('clusters');

        if ($clusters->isEmpty()) {
            return redirect()->route('cluster.create');
        }

        return $next($request);
    }
}
