<?php

namespace App\Listeners;

use App\Contracts\MustConfirmSubscription;
use App\Events\NewsletterSubscribed;

class SendEmailConfirmationNotification
{
    /**
     * Handle the event.
     *
     * @param NewsletterSubscribed $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->newsletterSubscription() instanceof MustConfirmSubscription && !$event->newsletterSubscription()->subscriptionConfirmed()) {
            $event->newsletterSubscription()->sendConfirmationEmailNotification();
        }
    }
}
