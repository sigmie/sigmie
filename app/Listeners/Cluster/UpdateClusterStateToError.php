<?php

declare(strict_types=1);

namespace App\Listeners\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Models\Cluster;
use App\Models\Project;

class UpdateClusterStateToError
{
    public function handle(ClusterHasFailed $event)
    {
        $cluster = Project::find($event->clusterId)->clusters->first();

        $cluster->update(['state' => Cluster::FAILED]);
    }
}
