<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Contracts\MustConfirmSubscription;
use App\Events\Newsletter\NewsletterSubscriptionWasCreated;

class SendEmailConfirmationNotification
{
    public function handle(NewsletterSubscriptionWasCreated $event): void
    {
        if (
            $event->newsletterSubscription instanceof MustConfirmSubscription
            && !$event->newsletterSubscription->subscriptionConfirmed()
        ) {
            $event->newsletterSubscription->sendConfirmationEmailNotification();
        }
    }
}
