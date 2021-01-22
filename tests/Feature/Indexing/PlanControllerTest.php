<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use WithRunningCluster;

    /**
     * @test
     */
    public function can_create_plan()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $this->post(route('indexing.plan'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
        ])->dump();

        $this->assertDatabaseHas(
            'indexing_plan',
            [
                'name' => 'Foo',
                'description' => 'Bar',
                'cluster_id' => $this->cluster->id,
                'type' => 'file',
            ]
        );
    }

    /**
    * @test
    */
    public function redirect_after_store_plan()
    {
        $this->assertTrue(false);
    }
}
