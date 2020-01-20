<?php

namespace App\Events;

use App\NewsletterSubscription;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class NewsletterSubscribed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $newsletterSubscription;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(NewsletterSubscription $newsletterSubscription)
    {
        $this->newsletterSubscription = $newsletterSubscription;
    }

    /**
     * Return the newsletter subscription instance
     *
     * @return NewsletterSubscription
     */
    public function newsletterSubscription()
    {
        return 'foo';

        return $this->newsletterSubscription;
    }
}
