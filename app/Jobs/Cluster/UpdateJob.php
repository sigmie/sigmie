<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster as AppCluster;
use App\Models\Project;
use Illuminate\Notifications\Notification;
use RuntimeException;
use Sigmie\App\Core\Contracts\Update;

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
