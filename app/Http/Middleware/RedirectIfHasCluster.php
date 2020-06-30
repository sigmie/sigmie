<?php

namespace App\Http\Middleware;

use App\Project;
use Closure;

class RedirectIfHasCluster
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $project = Project::find($request->get('project_id'));

        if ($project->clusters->isEmpty()) {
            return $next($request);
        }

        return redirect()->route('dashboard');
    }
}
