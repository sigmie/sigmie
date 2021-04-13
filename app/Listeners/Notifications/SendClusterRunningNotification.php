<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\Cluster\ClusterWasBooted;
use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\Cluster\ClusterIsRunning;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendClusterRunningNotification implements ShouldQueue
{
    public function handle(ClusterWasBooted $event): void
    {
        $project = Project::firstWhere('id', $event->projectId);

        $cluster = $project->clusters->first();

        $project->user->notify(new ClusterIsRunning($cluster->name, $project->name));
    }
}
