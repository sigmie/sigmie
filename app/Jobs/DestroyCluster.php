<?php

namespace App\Jobs;

use App\Models\Cluster;
use App\Events\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use Sigmie\App\Core\Cluster as CloudCluster;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClusterManagerFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;

class DestroyCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the destroy method. After
     * fire the cluster was created event.
     */
    public function handle()
    {
        $appCluster = Cluster::withTrashed()->find($this->clusterId);
        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        ClusterManagerFactory::create($appCluster->project->id)->destroy($coreCluster);

        $appCluster->update(['state' => Cluster::DESTROYED]);

        event(new ClusterWasDestroyed($appCluster->id));
    }
}
