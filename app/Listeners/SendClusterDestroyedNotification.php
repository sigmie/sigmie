<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasDestroyed;
use App\Notifications\ClusterWasDestroyed as ClusterWasDestroyedNotification;
use App\Repositories\ClusterRepository;

class SendClusterDestroyedNotification
{
    public function handle(ClusterWasDestroyed $event, ClusterRepository $clusters): void
    {
        $cluster = $clusters->findTrashed($event->clusterId);

        $user = $cluster->getAttribute('user');

        $projectName = $cluster->getAttribute('project')->getAttribute('name');

        $user->notify(new ClusterWasDestroyedNotification($projectName));
    }
}
