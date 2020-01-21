<?php

namespace App\Events;

use App\NewsletterSubscription;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NewsletterSubscribed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Newsletter subscription model
     *
     * @var NewsletterSubscription
     */
    public $newsletterSubscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(NewsletterSubscription $newsletterSubscription)
    {
        $this->newsletterSubscription = $newsletterSubscription;
    }
}
