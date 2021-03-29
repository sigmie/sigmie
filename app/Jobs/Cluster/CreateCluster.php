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

class CreateCluster extends ClusterJob
{
    public $tries = 1;

    private array $specs;

    public function __construct(int $clusterId, array $specs)
    {
        parent::__construct($clusterId);

        $this->specs = $specs;
    }

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the create method. After
     * fire the cluster was created event.
     */
    public function handle(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = Cluster::withTrashed()->firstWhere('id', $this->clusterId);
        $projectId = $appCluster->getAttribute('project')->getAttribute('id');
        $clusterId = $appCluster->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);
        $coreCluster->setCpus($this->specs['cores']);
        $coreCluster->setMemory($this->specs['memory']);
        $coreCluster->setDiskSize($this->specs['disk']);

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
