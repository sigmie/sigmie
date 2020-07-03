<?php

namespace App\Events;

use App\Cluster;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Queue\SerializesModels;

class ClusterWasCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $clusterId;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($clusterId)
    {
        $this->clusterId = $clusterId;
    }
}
