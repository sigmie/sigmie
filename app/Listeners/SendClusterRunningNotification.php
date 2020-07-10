<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasBooted;
use App\Models\Cluster;
use App\Notifications\ClusterIsRunning as ClusterIsRunningNotification;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    public function handle(ClusterWasBooted $event, ClusterRepository $clusters): void
    {
        $cluster = $clusters->find($event->clusterId);

        $user = $cluster->getAttribute('user');

        $projectName = $cluster->getAttribute('project')->getAttribute('name');

        $clusterName = $cluster->getAttribute('name');

        $user->notify(new ClusterIsRunningNotification($clusterName, $projectName));
    }
}
