<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\MustConfirmSubscription;
use App\Events\NewsletterSubscriptionWasCreated;

class SendEmailConfirmationNotification
{
    public function handle(NewsletterSubscriptionWasCreated $event): void
    {
        if ($event->newsletterSubscription instanceof MustConfirmSubscription && !$event->newsletterSubscription->subscriptionConfirmed()) {
            $event->newsletterSubscription->sendConfirmationEmailNotification();
        }
    }
}
