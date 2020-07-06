<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareProjectsToView
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
        if (Auth::check() === false) {
            return $next($request);
        }

        $projects = Auth::user()->projects->map(fn ($project) => $project->only(['name', 'id']));

        Inertia::share('projects', $projects);

        return $next($request);
    }
}
