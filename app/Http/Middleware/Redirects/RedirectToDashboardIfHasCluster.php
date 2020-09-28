<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use Closure;

class RedirectToDashboardIfHasCluster
{
    private ProjectRepository $projects;

    public function __construct(ProjectRepository $projectRepository)
    {
        $this->projects = $projectRepository;
    }

    /**
     * If the project id has already a cluster
     * redirect to the dashboard
     */
    public function handle($request, Closure $next)
    {
        $project =  $this->projects->find($request->get('project_id'));
        $clusters = $project->getAttribute('clusters');

        if ($clusters->isEmpty()) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
