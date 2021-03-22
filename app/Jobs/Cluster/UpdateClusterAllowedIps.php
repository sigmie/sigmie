<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class UpdateClusterAllowedIps implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $clusterId;

    public $tries = 3;

    public function __construct(int $clusterId)
    {
        $this->clusterId = $clusterId;
        $this->queue = 'long-running-queue';
    }

    public function uniqueId()
    {
        // That's how laravel builds the identifier
        // $key = 'laravel_unique_job:'.get_class($this->job).$uniqueId,
        return $this->clusterId;
    }

    public function middleware()
    {
        $middleware = new WithoutOverlapping($this->clusterId);
        $middleware->releaseAfter(20); // It takes around 18 sec to change the ips with 1 instance

        return [$middleware];
    }

    public function handle(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = Cluster::withTrashed()->where('id', $this->clusterId)->first();

        $projectId = $appCluster->getAttribute('project')->getAttribute('id');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        $allowedIps = $appCluster->allowedIps->pluck('ip')->toArray();

        $managerFactory->create($projectId)->update($coreCluster)->allowedIps($allowedIps);
    }
}
