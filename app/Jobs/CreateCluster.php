<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ClusterWasCreated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateCluster implements ShouldQueue
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
