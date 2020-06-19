<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareProjectToView
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
        $projectId = null;

        if ($request->get('project_id') !== null) {
            $projectId = (int) $request->get('project_id');
        }

        if ($projectId === null) {
            $projectId = Auth::user()->projects()->first()->id;
        }

        Inertia::share('project_id', $projectId);

        return $next($request);
    }
}
