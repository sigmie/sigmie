<?php

namespace App\Listeners;

use App\Events\NewsletterSubscribed;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Contracts\MustConfirmSubscription;

class SendEmailConfirmationNotification
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->newsletterSubscription() instanceof MustConfirmSubscription && !$event->newsletterSubscription()->subscriptionConfirmed()) {
            $event->newsletterSubscription()->sendConfirmationEmailNotification();
        }
    }
}
