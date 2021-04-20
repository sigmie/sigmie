<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Models\AbstractCluster;
use App\Models\ExternalCluster;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\UserSeeder;

trait WithRunningExternalCluster
{
    private User $user;

    private Project $project;

    private AbstractCluster $cluster;

    private function withRunningExternalCluster(User $user = null)
    {
        if (is_null($user)) {

            $userSeeder = new UserSeeder;
            $userSeeder->run();

            $user = User::find($userSeeder::$userId);
        }

        $this->user = $user;
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->cluster = ExternalCluster::factory()->create(['project_id' => $this->project->id]);

        $this->project->externalClusters()->attach($this->cluster);
    }
}
