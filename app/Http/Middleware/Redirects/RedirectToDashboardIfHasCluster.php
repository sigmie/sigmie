<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use App\Models\Project;
use Closure;

class RedirectToDashboardIfHasCluster
{
    /**
     * If the project id has already a cluster
     * redirect to the dashboard
     */
    public function handle($request, Closure $next)
    {
        $project = Project::find($request->get('project_id'));
        $clusters = $project->clusters;

        if ($clusters->isEmpty()) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
