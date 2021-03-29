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
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;

abstract class ClusterJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $clusterId;

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
        return [];
    }
}
