<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectToDashboardIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  Closure                 $next
     * @param  null|string              $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
