<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareProjectsToView
{
    /**
     * Share project names and id's to inertia
     */
    public function handle($request, Closure $next)
    {
        if (Auth::check() === false) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        $projects = $user->getAttribute('projects');

        $projects = $projects->map(fn ($project) => $project->only(['name', 'id']));

        Inertia::share('projects', $projects);

        return $next($request);
    }
}
