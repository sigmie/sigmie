<?php

declare(strict_types=1);

namespace App\Listeners\Notifications;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Models\Project;
use App\Notifications\Cluster\ClusterWasDestroyed as ClusterWasDestroyedNotification;

class SendClusterDestroyedNotification
{
    public function handle(ClusterWasDestroyed $event): void
    {
        $project = Project::firstWhere('id', $event->projectId);

        $project->user->notify(new ClusterWasDestroyedNotification($project->name));
    }
}
