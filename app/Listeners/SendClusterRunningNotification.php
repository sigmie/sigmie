<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasBooted as ClusterIsRunningEvent;
use App\Models\Cluster;
use App\Notifications\ClusterIsRunning as ClusterIsRunningNotification;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    public function handle(ClusterIsRunningEvent $event, ClusterRepository $clusters): void
    {
        $cluster = $clusters->find($event->clusterId);
        $user = $cluster->project->user;

        $user->notify(new ClusterIsRunningNotification($cluster->name, $cluster->project->name));
    }
}
