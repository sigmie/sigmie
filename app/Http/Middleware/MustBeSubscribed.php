<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class MustBeSubscribed
{
    public function handle($request, Closure $next)
    {
        $planName = config('services.paddle.plan_name');

        if (Auth::user()->subscribed($planName)) {
            return $next($request);
        }

        return redirect()->route('subscription.missing');
    }
}
