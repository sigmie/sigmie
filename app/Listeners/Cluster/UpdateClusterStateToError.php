<?php

declare(strict_types=1);

namespace App\Listeners\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;

class UpdateClusterStateToError
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusters)
    {
        $this->clusters = $clusters;
    }

    public function handle(ClusterHasFailed $event)
    {
        $this->clusters->update($event->clusterId, ['state' => Cluster::FAILED]);
    }
}
