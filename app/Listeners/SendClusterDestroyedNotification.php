<?php declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasDestroyed;
use App\Models\Cluster;
use App\Notifications\ClusterWasDestroyed as NotificationsClusterWasDestroyed;

class SendClusterDestroyedNotification
{
    public function handle(ClusterWasDestroyed $event): void
    {
        $user = Cluster::withTrashed()->where('id', $event->clusterId)->first()->project->user;

        $user->notify(new NotificationsClusterWasDestroyed($event->clusterId));
    }
}
