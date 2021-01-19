<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClusterPoliciesTest extends TestCase
{
    /**
     * @test
     */
    public function user_cant_create_cluster_if_he_already_has_one()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);
        $cluster = Cluster::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user);

        $response = $this->get(route('cluster.create'));

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function user_cant_store_cluster_if_he_already_has_one()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);
        $cluster = Cluster::factory()->create(['project_id' => $project->id]);

        $this->actingAs($user);

        $response = $this->post(route('cluster.store'), [
            'name' => 'foo',
            'nodes_count' => '3',
            'data_center' => 'europe',
            'username' => 'bar',
            'password' => 'baz',
            'project_id' => '1'
        ]);

        $response->assertForbidden();
    }
}
