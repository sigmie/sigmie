<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\Cluster\ClusterBasicAuthWasUpdated;
use Illuminate\Notifications\Notification;
use Sigmie\App\Core\Contracts\Update;

class UpdateClusterBasicAuth extends UpdateJob
{
    protected function notification(Project $project): ?Notification
    {
        return new ClusterBasicAuthWasUpdated($project->name);
    }

    protected function update(Update $update, Cluster $appCluster)
    {
        $update->basicAuth(
            $appCluster->username,
            decrypt($appCluster->password)
        );
    }
}
