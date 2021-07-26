<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\Cluster\ClusterAllowedIpsWereUpdated;
use Illuminate\Notifications\Notification;
use Sigmie\App\Core\Contracts\Update;

class UpdateClusterAllowedIps extends UpdateJob
{
    protected function notification(Project $project): ?Notification
    {
        return new ClusterAllowedIpsWereUpdated($project->name);
    }

    protected function update(Update $update, Cluster $appCluster)
    {
        $allowedIps = $appCluster->allowedIps->pluck('ip')->toArray();

        $update->allowedIps($allowedIps);
    }
}
