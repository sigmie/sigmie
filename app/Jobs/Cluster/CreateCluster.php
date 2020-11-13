<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Events\Cluster\ClusterWasCreated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class CreateCluster implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private int $clusterId;

    private $clusters;

    private array $specs;

    public function __construct(int $clusterId, array $specs)
    {
        $this->clusterId = $clusterId;
        $this->specs = $specs;
        $this->queue = 'long-running-queue';
    }

    public function getClusterId()
    {
        return $this->clusterId;
    }

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the create method. After
     * fire the cluster was created event.
     */
    public function handle(ClusterRepository $clusters, ClusterManagerFactory $managerFactory): void
    {
        $appCluster = $clusters->findTrashed($this->clusterId);
        $projectId = $appCluster->getAttribute('project')->getAttribute('id');
        $clusterId = $appCluster->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);
        $coreCluster->setCpus($this->specs['cores']);
        $coreCluster->setMemory($this->specs['memory']);
        $coreCluster->setDiskSize($this->specs['disk']);

        $managerFactory->create($projectId)->create($coreCluster);

        $clusters->update($this->clusterId, ['state' => Cluster::CREATED]);

        event(new ClusterWasCreated($clusterId));
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $throwable)
    {
        event(new ClusterHasFailed($this->clusterId));
    }
}
