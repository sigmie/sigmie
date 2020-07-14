<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Support\Facades\Auth;

class AssignProject
{
    /**
     * Assign project to the route if it was called without one
     */
    public function handle($request, Closure $next)
    {
        $project = $request->route('project');

        if ($project instanceof Project) {
            return $next($request);
        }

        // Get the first project idk
        $projectId = Auth::user()->getAttribute('projects')->first()->getAttribute('id');

        $routeName = $request->route()->getName();

        return redirect()->route($routeName, ['project' => $projectId]);
    }
}
