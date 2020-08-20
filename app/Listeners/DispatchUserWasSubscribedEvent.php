<?php

namespace App\Listeners;

use App\Events\UserWasSubscribed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Paddle\Events\WebhookHandled;

class DispatchUserWasSubscribedEvent implements ShouldQueue
{
    public function handle(WebhookHandled $event)
    {
        $paddleEvent = $event->payload['alert_name'];

        if ($paddleEvent === 'subscription_created') {

            $checkoutId = $event->payload['checkout_id'];

            event(new UserWasSubscribed($checkoutId));
        }
    }
}
