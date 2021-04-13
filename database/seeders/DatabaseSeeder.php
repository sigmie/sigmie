<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AllowedIp;
use App\Models\Cluster;
use App\Models\ClusterName;
use App\Models\FileType;
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
            WithRunningCluster::class
        ]);

        // $name = ClusterName::create([
        //     'name' => 'test',
        //     'cluster_id' => $externalCluster->id,
        //     'cluster_type' => $externalCluster->getMorphClass()
        // ]);

        // $externalCluster->update([
        //     'name_id' => $name->id
        // ]);

        // $allowedIps = AllowedIp::factory()->create(['cluster_id' => $cluster->id]);
        // $allowedIps = AllowedIp::factory()->create(['cluster_id' => $cluster->id]);
        // $allowedIps = AllowedIp::factory()->create(['cluster_id' => $cluster->id]);
        // $allowedIps = AllowedIp::factory()->create(['cluster_id' => $cluster->id]);

        // $type = FileType::create([
        //     'location' => 'https://gist.githubusercontent.com/nicoorfi/e1e70646515e983f9563fbcb174f52ff/raw/1dc1e7ae7a1ff57f047c4eb7a1dfeef9e27aabe4/docs.sigmie.content.json',
        //     'index_alias' => 'docs'
        // ]);

        // $plan = IndexingPlan::factory()->create([
        //     'cluster_id' => $cluster->id,
        //     'project_id' => $project->id,
        //     'user_id' => $project->user->id,
        //     'name' => 'Sigmie Docs'
        // ]);

        // $plan->type()->associate($type)->save();
    }
}
