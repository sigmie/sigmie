<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use App\Models\Project;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirecToSameRouteWithProject
{
    /**
     * Assign project to the route if it was called without one
     */
    public function handle($request, Closure $next)
    {
        $project = $request->route('project');

        if ($project !== null) {
            return $next($request);
        }

        // Get the first project id
        $project = Auth::user()->getAttribute('projects')->first();

        if ($project instanceof Project) {
            $projectId = $project->getAttribute('id');

            $routeName = $request->route()->getName();

            return redirect()->route($routeName, ['project' => $projectId]);
        }


        return redirect()->route('project.create');
    }
}
