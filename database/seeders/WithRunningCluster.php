<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Cluster;
use App\Models\Project;
use App\Models\Subscription;

class WithRunningCluster extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);
        $cluster = Cluster::factory()->create(['project_id' => $project->id]);
    }
}
