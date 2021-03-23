<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\Cluster\ClusterWasBooted;
use App\Models\Cluster;
use App\Notifications\Cluster\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use Exception;
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
        $cluster = $this->clusters->find($event->projectId);

        if ($cluster instanceof Cluster) {
            $user = $cluster->findUser();

            $projectName = $cluster->getAttribute('project')->getAttribute('name');

            $clusterName = $cluster->getAttribute('name');

            $user->notify(new ClusterIsRunning($clusterName, $projectName));

            return;
        }

        throw new Exception("Cluster with the id {$event->projectId} was not found.");
    }
}
