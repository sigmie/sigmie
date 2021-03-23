<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;

trait WithDestroyedCluster
{
    private User $user;

    private Project $project;

    private Cluster $cluster;

    private function withDestroyedCluster()
    {
        $this->user = Subscription::factory()->create()->billable;
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->cluster = Cluster::factory()->create([
            'project_id' => $this->project->id,
            'state' => Cluster::DESTROYED
        ]);

        $this->project->internalClusters()->attach($this->cluster);
    }
}
