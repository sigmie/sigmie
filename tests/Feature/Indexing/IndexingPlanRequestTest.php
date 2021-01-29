<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class IndexingPlanRequestTest extends TestCase
{
    use WithRunningCluster, WithIndexingPlan;

    /**
     * @test
     */
    public function plan_store_is_validated()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $this->post(route('indexing.plan.store'), [
            'name' => 'F',
        ])->assertSessionHasErrors();

        $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'invalid', //Invalid value
        ])->assertSessionHasErrors();

        $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
            'location' => 'https://github.com',
            'index_alias' => 'shop_id_001',
        ])->assertSessionHasNoErrors();
    }

    /**
     * @test
     */
    public function plan_update_is_validated()
    {
        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $res = $this->put(
            route('indexing.plan.update', ['plan' => $this->indexingPlan->id]),
            [
                'index_alias' => 'foo_bar'
            ]
        );

        $res->assertSessionHasNoErrors(); // No field is required

        $res = $this->put(
            route('indexing.plan.update', ['plan' => $this->indexingPlan->id]),
            ['name' => 'o']
        )->assertSessionHasErrors();

        $res = $this->put(
            route('indexing.plan.update', ['plan' => $this->indexingPlan->id]),
            [
                'type' => 'invalid', //Invalid value
            ]
        )->assertSessionHasErrors();

        $this->put(
            route('indexing.plan.update', ['plan' => $this->indexingPlan->id]),
            [
                'name' => 'Valid name',
                'location' => 'https://github.com',
                'index_alias' => 'shop_id_001',
            ]
        )->assertSessionHasNoErrors();
    }
}
