<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\AbstractCluster;
use App\Models\ExternalCluster;
use App\Models\Project;
use App\Models\User;

trait WithRunningExternalCluster
{
    use WithSubscribedUser;

    private User $user;

    private Project $project;

    private AbstractCluster $cluster;

    private function withRunningExternalCluster(
        array $project = [],
        array $cluster = [],
        array $user = []
    ) {
        $this->withSubscribedUser();

        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->cluster = ExternalCluster::factory()->create(['project_id' => $this->project->id]);

        $this->project->externalClusters()->attach($this->cluster);
    }
}
