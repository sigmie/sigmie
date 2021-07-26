<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Events\Cluster\ClusterWasCreated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use Throwable;

class CreateCluster extends ClusterJob
{
    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the create method. After
     * fire the cluster was created event.
     */
    public function handleJob(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = Cluster::withTrashed()->firstWhere('id', $this->clusterId);
        $projectId = $appCluster->getAttribute('project')->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $design = $managerFactory->create($projectId)->create($coreCluster);

        $appCluster->update([
            'state' => Cluster::CREATED,
            'design' => $design
        ]);

        event(new ClusterWasCreated($projectId));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable)
    {
        $appCluster = Cluster::withTrashed()->firstWhere('id', $this->clusterId);
        $appCluster->update(['state' => Cluster::DESTROYED]);

        event(new ClusterHasFailed($this->clusterId));
    }
}
