<?php

namespace App\Listeners;

use App\Models\Cluster;
use App\Events\ClusterWasBooted as ClusterIsRunningEvent;
use App\Notifications\ClusterIsRunning as ClusterIsRunningNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    public function handle(ClusterIsRunningEvent $event): void
    {
        $user = Cluster::find($event->clusterId)->project->user;

        $user->notify(new ClusterIsRunningNotification($event->clusterId));
    }
}
