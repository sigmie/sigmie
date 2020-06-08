<?php

namespace App\Http\Middleware;

use Closure;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

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

        $projects = Auth::user()->projects->only(['name', 'id']);

        Inertia::share('projects', $projects);

        return $next($request);
    }
}
