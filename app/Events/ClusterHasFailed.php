<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClusterHasFailed
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
