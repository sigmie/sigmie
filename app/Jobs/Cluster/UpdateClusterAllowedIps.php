<?php

declare(strict_types=1);

namespace App\Jobs\Cluster;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterAdapter;
use App\Helpers\ClusterManagerFactory;
use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\Cluster\ClusterAllowedIpsWereUpdated;
use App\Repositories\ClusterRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Sigmie\App\Core\Contracts\Update;

class UpdateClusterAllowedIps extends UpdateJob
{
    protected function notification(Project $project): Notification
    {
        return new ClusterAllowedIpsWereUpdated($project->name);
    }

    protected function update(Update $update, Cluster $appCluster)
    {
        $allowedIps = $appCluster->allowedIps->pluck('ip')->toArray();

        $update->allowedIps($allowedIps);
    }
}
