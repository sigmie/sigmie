<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareProjectToView
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        $projectId = $request->get('project_id');

        $project = Auth::user()->getAttribute('projects')->first();

        if ($projectId === null && $project instanceof Project) {
            $projectId = $project->getAttribute('id');
        }

        Inertia::share('project_id', $projectId);

        return $next($request);
    }
}
