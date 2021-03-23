<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WithDeletedCluster extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(ClusterRepository $clusterRepository)
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
