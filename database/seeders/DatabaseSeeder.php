<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\IndexingPlan;
use App\Models\Project;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class
        ]);

        $project = Project::factory()->create(['user_id' => 1]);
        $cluster = Cluster::factory()->create(['project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
        $plan = IndexingPlan::factory()->create(['cluster_id' => $cluster->id, 'project_id' => $project->id]);
    }
}
