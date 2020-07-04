<?php

namespace App\Listeners;

use App\Cluster;
use App\Events\ClusterIsRunning as ClusterIsRunningEvent;
use App\Notifications\ClusterIsRunning as ClusterIsRunningNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(ClusterIsRunningEvent $event)
    {
        $user = Cluster::find($event->clusterId)->project->user;

        $user->notify(new ClusterIsRunningNotification($event->clusterId));
    }
}
