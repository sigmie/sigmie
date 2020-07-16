<?php

namespace Tests\Feature;

use App\Models\Cluster;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ClusterPoliciesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function user_cant_create_cluster_if_he_already_has_one()
    {
        $cluster = factory(Cluster::class)->create();
        $user = $cluster->findUser();

        $this->actingAs($user);

        $response = $this->get(route('cluster.create'));

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function user_cant_store_cluster_if_he_already_has_one()
    {
        $cluster = factory(Cluster::class)->create();
        $user = $cluster->findUser();

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
