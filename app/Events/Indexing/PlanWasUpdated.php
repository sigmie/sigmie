<?php

declare(strict_types=1);

namespace App\Events\Indexing;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlanWasUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $planId;

    public function __construct(int $clusterId)
    {
        $this->planId = $clusterId;
    }

    public function broadcastOn()
    {
        return new PrivateChannel("plan.{$this->planId}");
    }

    public function broadcastAs()
    {
        return 'plan.updated';
    }
}
