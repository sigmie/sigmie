<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasBooted as ClusterIsRunningEvent;
use App\Models\Cluster;
use App\Notifications\ClusterIsRunning as ClusterIsRunningNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    public function handle(ClusterIsRunningEvent $event): void
    {
        $user = Cluster::find($event->clusterId)->project->user;

        $user->notify(new ClusterIsRunningNotification($event->clusterId));
    }
}
