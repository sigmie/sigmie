<?php

declare(strict_types=1);

namespace App\Events\Newsletter;

use App\Models\NewsletterSubscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewsletterSubscriptionWasCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public NewsletterSubscription $newsletterSubscription;

    public function __construct(NewsletterSubscription $newsletterSubscription)
    {
        $this->newsletterSubscription = $newsletterSubscription;
    }
}
