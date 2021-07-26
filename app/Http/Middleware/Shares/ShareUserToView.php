<?php

declare(strict_types=1);

namespace App\Http\Middleware\Shares;

use Closure;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ShareUserToView
{
    /**
     * Share user information to inertia view
     */
    public function handle($request, Closure $next)
    {
        $user = null;

        if (Auth::check()) {
            $user = Auth::user()->only(['id', 'avatar_url']);
        }

        Inertia::share('user', $user);

        return $next($request);
    }
}
