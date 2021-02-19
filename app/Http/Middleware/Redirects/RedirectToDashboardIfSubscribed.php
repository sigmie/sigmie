<?php

declare(strict_types=1);

namespace App\Http\Middleware\Redirects;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectToDashboardIfSubscribed
{
    public function handle($request, Closure $next)
    {
        $planName = config('services.paddle.plan_name');
        $user = Auth::user();

        $subscription = $user->subscription($planName);

        if ($subscription !== null && $user->subscription($planName)->cancelled() === false) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
