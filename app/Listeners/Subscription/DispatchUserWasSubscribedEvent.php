<?php

namespace App\Listeners\Subscription;

use App\Events\Subscription\UserWasSubscribed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Paddle\Events\WebhookHandled;

class DispatchUserWasSubscribedEvent implements ShouldQueue
{
    public function handle(WebhookHandled $event)
    {
        $paddleEvent = $event->payload['alert_name'];

        if ($paddleEvent === 'subscription_created') {

            $passthrough = json_decode($event->payload['passthrough'], true);

            $userId = (int) $passthrough['billable_id'];

            event(new UserWasSubscribed($userId));
        }
    }
}
