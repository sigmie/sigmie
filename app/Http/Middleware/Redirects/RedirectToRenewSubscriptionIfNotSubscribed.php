<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectToRenewSubscriptionIfNotSubscribed
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