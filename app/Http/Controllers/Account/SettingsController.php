<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(string $section = 'account')
    {
        $user = Auth::user();

        $planName = config('services.paddle.plan_name');
        $data = [];
        $data['subscription'] = ['was_subscribed' => false];

        $subscription = $user->subscription($planName);

        if ($subscription !== null) {
            $data['subscription'] = ['was_subscribed' => true];
            $info = $subscription->paddleInfo();
            $method = $info['payment_information']['payment_method'];
            $nextPayment = $subscription->nextPayment();
            $lastPayment = $subscription->lastPayment();

            if ($method !== 'paypal') {
                $data['subscription']['card_brand'] = $subscription->cardBrand();
                $data['subscription']['card_last_four'] = $subscription->cardLastFour();
                $data['subscription']['card_expire_date'] = $subscription->cardExpirationDate();
            }

            $data['subscription']['canceled'] = $subscription->cancelled();
            $data['subscription']['ends_at'] = $subscription->getAttribute('ends_at');
            $data['subscription']['plan'] = ucfirst($planName);
            $data['subscription']['payment_method'] = $method;
            $data['subscription']['email'] = $subscription->paddleEmail();
            $data['subscription']['on_trial'] = $subscription->onTrial();
            $data['subscription']['trail_ends_at'] = $subscription->getAttribute('trial_ends_at');

            if ($lastPayment !== null) {
                $data['subscription']['last_payment'] = $lastPayment->date();
            }

            if ($nextPayment !== null) {
                $data['subscription']['next_payment'] = $nextPayment->date();
            }
        }

        $data['account'] = $user->only(['username', 'email', 'avatar_url', 'created_at']);


        return Inertia::render('account/settings', ['section' => $section, 'data' => $data]);
    }
}
