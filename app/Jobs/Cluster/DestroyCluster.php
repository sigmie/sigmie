<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;

class DestroyCluster extends ClusterJob
{
    public $tries = 1;

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the destroy method. After
     * fire the cluster was created event.
     */
    public function handle(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = Cluster::withTrashed()->firstWhere('id', $this->clusterId);

        $projectId = $appCluster->getAttribute('project')->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $managerFactory->create($projectId)->destroy($coreCluster);

        $appCluster->update([
            'state' => Cluster::DESTROYED,
            'design' => []
        ]);

        $appCluster->allowedIps()->delete();

        event(new ClusterWasDestroyed($projectId));
    }
}
