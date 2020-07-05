<?php

namespace App\Listeners;

use App\Models\Cluster;
use App\Events\ClusterWasDestroyed;
use App\Notifications\ClusterWasDestroyed as NotificationsClusterWasDestroyed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendClusterDestroyedNotification
{
    public function handle(ClusterWasDestroyed $event): void
    {
        $user = Cluster::withTrashed()->where('id', $event->clusterId)->first()->project->user;

        $user->notify(new NotificationsClusterWasDestroyed($event->clusterId));
    }
}
