<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ClusterWasBooted;
use App\Models\Cluster;
use App\Notifications\ClusterIsRunning;
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
        $cluster = $this->clusters->find($event->clusterId);

        if ($cluster instanceof Cluster) {

            $user = $cluster->findUser();

            $projectName = $cluster->getAttribute('project')->getAttribute('name');

            $clusterName = $cluster->getAttribute('name');

            $user->notify(new ClusterIsRunning($clusterName, $projectName));

            return;
        }

        throw new Exception("Cluster with the id {$event->clusterId} was not found.");
    }
}
