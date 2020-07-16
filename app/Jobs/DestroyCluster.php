<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DestroyCluster implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    public function getClusterId(): int
    {
        return $this->clusterId;
    }

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the destroy method. After
     * fire the cluster was created event.
     */
    public function handle(ClusterRepository $clusters, ClusterManagerFactory $managerFactory): void
    {
        $appCluster = $clusters->findTrashed($this->clusterId);
        $projectId = $appCluster->getAttribute('project')->getAttribute('id');
        $clusterId = $appCluster->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $managerFactory->create($projectId)->destroy($coreCluster);

        $clusters->updateTrashed($clusterId, ['state' => Cluster::DESTROYED]);

        event(new ClusterWasDestroyed($clusterId));
    }
}
