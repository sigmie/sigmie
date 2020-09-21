<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Paddle\Subscription;
use Tests\Helpers\ElasticsearchCleanup;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use DatabaseTransactions;
    use ElasticsearchCleanup;

    /**
     * @test
     */
    public function dashboard_data_returns_cluster_info()
    {
        $user = factory(Subscription::class)->create()->billable;
        $project = factory(Project::class)->create(['user_id' => $user->id]);
        $cluster = factory(Cluster::class)->create(['project_id' => $project->id]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard.data', ['project' => $project->id]));

        $response->assertJson([
            'clusterState' => 'running',
            'clusterId' => $cluster->id,
            'indices' => [],
            'clusterInfo' => [
                'health' => 'green',
                'nodesCount' => 1,
                'name' => 'sigmie-dev',
            ]
        ]);
    }
}
