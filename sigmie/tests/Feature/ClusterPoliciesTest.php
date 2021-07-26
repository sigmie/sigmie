<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class ClusterPoliciesTest extends TestCase
{
    use WithRunningExternalCluster;

    /**
     * @test
     */
    public function user_cant_create_cluster_if_he_already_has_one()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $response = $this->get(route('cluster.create'));

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function user_cant_store_cluster_if_he_already_has_one()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $response = $this->post(route('cluster.store'), [
            'name' => 'foo',
            'nodes_count' => '3',
            'data_center' => 'europe',
            'username' => 'bar',
            'password' => '1234',
            'region_id' => 1,
            'memory' => '1024',
            'disk' => '15',
            'cores' => '2',
            'project_id' => '1'
        ])->assertSessionHasNoErrors();

        $response->assertForbidden();
    }
}
