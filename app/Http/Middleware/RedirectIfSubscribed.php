<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfSubscribed
{
    public function handle($request, Closure $next)
    {
        $planName = config('services.paddle.plan_name');

        if (Auth::user()->subscribed($planName)) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
