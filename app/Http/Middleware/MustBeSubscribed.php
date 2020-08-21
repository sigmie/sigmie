<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class MustBeSubscribed
{
    public function handle($request, Closure $next)
    {
        $planName = config('services.paddle.plan_name');
        $user = Auth::user();

        $subscription = $user->subscription($planName);

        if ($subscription !== null && $user->subscription($planName)->cancelled()) {
            return redirect()->route('subscription.expired');
        }

        if ($user->subscribed($planName)) {
            return $next($request);
        }


        return redirect()->route('subscription.missing');
    }
}
