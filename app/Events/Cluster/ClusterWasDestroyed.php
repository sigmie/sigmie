<?php

declare(strict_types=1);

namespace App\Events\Cluster;

use App\Models\Project;
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

    public int $projectId;

    public function __construct(int $clusterId)
    {
        $this->projectId = $clusterId;
    }

    public function broadcastOn()
    {
        $cluster = Project::find($this->projectId)->clusters->first();

        return new PrivateChannel("{$cluster->getMorphClass()}.{$cluster->id}");
    }

    public function broadcastAs()
    {
        return 'cluster.destroyed';
    }
}
