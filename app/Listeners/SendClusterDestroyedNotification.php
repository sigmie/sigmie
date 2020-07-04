<?php

namespace App\Listeners;

use App\Cluster;
use App\Events\ClusterWasDestroyed;
use App\Notifications\ClusterWasDestroyed as NotificationsClusterWasDestroyed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendClusterDestroyedNotification
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ClusterWasDestroyed $event)
    {
        $user = Cluster::withTrashed()->where('id', $event->clusterId)->first()->project->user;

        $user->notify(new NotificationsClusterWasDestroyed($event->clusterId));
    }
}
