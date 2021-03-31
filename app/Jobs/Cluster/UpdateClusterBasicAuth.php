<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Events\Cluster\ClusterWasUpdated;
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

class UpdateClusterBasicAuth extends ClusterJob
{
    public $tries = 10;

    public function actionId(): string
    {
        return '';
    }

    public function handle(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = Cluster::withTrashed()->where('id', $this->clusterId)->first();

        $projectId = $appCluster->getAttribute('project')->getAttribute('id');

        // $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        // $allowedIps = $appCluster->allowedIps->pluck('ip')->toArray();

        // $managerFactory->create($projectId)->update($coreCluster)->allowedIps($allowedIps);

        // $appCluster->update(['state' => Cluster::RUNNING]);

        // event(new ClusterWasUpdated($projectId));
        sleep(15);

        event(new ClusterWasUpdated($projectId));

        $this->releaseAction();
    }
}
