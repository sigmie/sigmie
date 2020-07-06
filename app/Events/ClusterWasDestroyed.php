<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClusterWasDestroyed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("cluster.{$this->clusterId}");
    }
}
