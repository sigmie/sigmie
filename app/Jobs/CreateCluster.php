<?php

namespace App\Jobs;

use App\Models\Cluster;
use App\Events\ClusterCreated;
use App\Events\ClusterWasCreated;
use App\Helpers\ClusterAdapter;
use Illuminate\Support\Str;
use Sigmie\App\Core\Cluster as CloudCluster;
use Illuminate\Bus\Queueable;
use Sigmie\App\Core\ClusterManager;
use Illuminate\Queue\SerializesModels;
use App\Helpers\ClusterManagerFactory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Sigmie\App\Core\Cloud\Regions\America;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Europe;

class CreateCluster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $clusterId;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
    }

    /**
     * Map the application Cluster instance to the sigmie Cluster instance
     * initialize the Cluster manager and call the create method. After
     * fire the cluster was created event.
     */
    public function handle(): void
    {
        $appCluster = Cluster::withTrashed()->where('id', $this->clusterId)->first();
        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        ClusterManagerFactory::create($appCluster->project->id)->create($coreCluster);

        $appCluster->update(['state' => Cluster::CREATED]);

        event(new ClusterWasCreated($appCluster->id));
    }
}
