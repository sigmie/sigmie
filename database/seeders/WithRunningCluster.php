<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ExternalCluster;
use App\Models\Project;
use Illuminate\Database\Seeder;

class WithRunningCluster extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            UserSeeder::class
        ]);

        $project = Project::factory()->create(['user_id' => UserSeeder::$userId]);
        $externalCluster = ExternalCluster::factory()->create(['project_id' => $project->id]);

        $project->externalClusters()->attach($externalCluster);
    }
}
