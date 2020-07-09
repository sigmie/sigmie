<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasDestroyed;
use App\Models\Cluster;
use App\Notifications\ClusterWasDestroyed as NotificationsClusterWasDestroyed;
use App\Repositories\ClusterRepository;

class SendClusterDestroyedNotification
{
    public function handle(ClusterWasDestroyed $event, ClusterRepository $clusters): void
    {
        $cluster = $clusters->findTrashed($event->clusterId);

        $cluster->user->notify(new NotificationsClusterWasDestroyed($cluster->project->name));
    }
}
