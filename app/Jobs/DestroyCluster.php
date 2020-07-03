<?php

namespace App\Jobs;

use App\Cluster;
use App\Events\ClusterCreated;
use App\Events\ClusterWasCreated;
use App\Events\ClusterWasDestroyed;
use Illuminate\Support\Str;
use Sigmie\App\Core\Cluster as CloudCluster;
use Illuminate\Bus\Queueable;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Queue\SerializesModels;
use App\Factories\ClusterManagerFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;

class DestroyCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Provider credentials
     *
     * @var array
     */
    private int $clusterId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $cluster = Cluster::withTrashed()->find($this->clusterId);
        $cloudCluster = new CloudCluster();
        $projectId = $cluster->project->id;

        $cloudCluster->setName($cluster->name);

        if ($cluster->data_center === 'europe') {
            $cloudCluster->setRegion(new Europe);
        }

        if ($cluster->data_center === 'asia') {
            $cloudCluster->setRegion(new Asia);
        }

        if ($cluster->data_center === 'america') {
            $cloudCluster->setRegion(new America);
        }

        $cloudCluster->setDiskSize(15);
        $cloudCluster->setNodesCount($cluster->nodes_count);

        $cloudCluster->setUsername($cluster->username);
        $cloudCluster->setPassword(decrypt($cluster->password));

        $manager = (new ClusterManagerFactory)->create($projectId);
        $manager->destroy($cloudCluster);

        $cluster->state = Cluster::DESTROYED;
        $cluster->save();

        event(new ClusterWasDestroyed($cluster->id));
    }
}
