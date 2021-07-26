<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Database\Seeder;

class WithDeletedCluster extends Seeder
{
    public function run()
    {
        $userId = 1;

        $this->call([
            UserSeeder::class
        ]);

        $project = Project::factory()->create(['user_id' => $userId]);
        $cluster = Cluster::factory()->create([
            'project_id' => $project->id,
            'state' => 'destroyed'
        ]);

        $cluster->delete();
    }
}
