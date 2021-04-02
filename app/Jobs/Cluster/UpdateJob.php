<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterUpdateLockAcquired;
use App\Events\Cluster\ClusterWasDestroyed;
use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster as AppCluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;
use Sigmie\App\Core\Contracts\Update;
use Throwable;

abstract class UpdateJob extends ClusterJob
{
    abstract protected function update(Update $update, AppCluster $cluster);

    abstract protected function notification(Project $project): Notification;

    protected function handleJob(ClusterManagerFactory $managerFactory): void
    {
        $appCluster = AppCluster::withTrashed()->where('id', $this->clusterId)->first();

        $project = $appCluster->getAttribute('project');

        $coreCluster = ClusterAdapter::toCoreCluster($appCluster);

        if ($appCluster->state !== AppCluster::RUNNING) {
            throw new RuntimeException("Can't update a cluster which isn't running.");
        }

        sleep(5);
        // $update = $managerFactory->create($project->id)->update($coreCluster);

        // $this->update($update, $appCluster);

        event(new ClusterWasUpdated($project->id));

        $notification = $this->notification($project);

        $project->user->notify($notification);
    }
}
