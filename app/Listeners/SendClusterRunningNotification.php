<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasBooted;
use App\Notifications\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    private ClusterRepository $clusters;

    public function __construct(ClusterRepository $clusters)
    {
        $this->clusters = $clusters;
    }

    public function handle(ClusterWasBooted $event): void
    {
        $cluster = $this->clusters->find($event->clusterId);

        $user = $cluster->findUser();

        $projectName = $cluster->getAttribute('project')->getAttribute('name');

        $clusterName = $cluster->getAttribute('name');

        $user->notify(new ClusterIsRunning($clusterName, $projectName));
    }
}
