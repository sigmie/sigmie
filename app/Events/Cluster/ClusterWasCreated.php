<?php

declare(strict_types=1);

namespace App\Events\Cluster;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClusterWasCreated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }
}
