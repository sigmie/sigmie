<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUser;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Laravel\Paddle\Receipt;

class SubscriptionController extends Controller
{
    public function create()
    {
        $user = Auth::user();
        $planId = config('services.paddle.plan_id');
        $planName = config('services.paddle.plan_name');
        $trailDays = ($user->subscription($planName) === null) ? 14 : 0;

        $paylink = $user->newSubscription($planName, $planId)
            ->trialDays($trailDays)
            ->create();

        return Inertia::render('subscription/create', [
            'paylink' => $paylink,
            'vendor' => (int) config('services.paddle.vendor_id')
        ]);
    }

    public function await(Request $request)
    {
        $checkoutId = $request->get('checkout');
        $receipt = Receipt::firstWhere('checkout_id', $request->get('checkout'));

        if ($receipt !== null && $receipt->getAttribute('billable')->subscribed('hobby')) {
            return redirect()->route('project.create');
        }

        return Inertia::render('subscription/await', ['checkoutId' => $checkoutId]);
    }

    public function missing()
    {
        return Inertia::render('subscription/missing');
    }

    public function check()
    {
        $subscribed = Auth::user()->subscribed(config('services.paddle.plan_name'));

        return ['subscribed' => $subscribed];
    }

    public function expired()
    {
        return Inertia::render('subscription/expired');
    }
}