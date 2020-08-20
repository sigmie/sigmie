<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserWasSubscribed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private $checkoutId;

    public function __construct($checkoutId)
    {
        $this->checkoutId = $checkoutId;
    }

    public function broadcastOn()
    {
        return new Channel($this->checkoutId);
    }
}
