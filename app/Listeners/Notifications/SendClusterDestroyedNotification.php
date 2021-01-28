<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Notifications\Cluster\ClusterWasDestroyed as ClusterWasDestroyedNotification;
use App\Repositories\ClusterRepository;

class SendClusterDestroyedNotification
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusterRepository)
    {
        $this->clusters = $clusterRepository;
    }

    public function handle(ClusterWasDestroyed $event): void
    {
        $cluster = $this->clusters->findTrashed($event->clusterId);

        $user = $cluster->findUser();

        $projectName = $cluster->getAttribute('project')->getAttribute('name');

        $user->notify(new ClusterWasDestroyedNotification($projectName));
    }
}