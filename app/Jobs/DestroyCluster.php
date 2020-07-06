<?php declare(strict_types=1);

namespace App\Jobs;

use App\Events\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
