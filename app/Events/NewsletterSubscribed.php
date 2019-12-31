<?php

namespace App\Events;

use App\NewsletterSubscription;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
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
        return $this->newsletterSubscription;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
