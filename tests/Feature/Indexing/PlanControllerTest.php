<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Models\IndexingPlan;
use App\Models\IndexingPlanDetails;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use WithRunningCluster, WithNotSubscribedUser, WithIndexingPlan;

    /**
     * @test
     */
    public function destroy_plan()
    {
        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $this->delete(route('indexing.plan.destroy', ['plan' => $this->indexingPlan->id]));

        $this->assertNull(IndexingPlan::find($this->indexingPlan->id));
    }

    /**
     * @test
     */
    public function update_plan()
    {
        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $this->put(route('indexing.plan.update', ['plan' => $this->indexingPlan->id]), [
            'name' => 'Johnyy'
        ]);

        $this->indexingPlan->refresh();

        $this->assertEquals('Johnyy', $this->indexingPlan->name);
    }


    /**
     * @test
     */
    public function create_plan()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
            'frequency' => 'daily',
            'index_alias' => 'demo_index_0111',
            'location' => 'https://google.com'
        ]);

        $plan = $this->cluster->plans->first();

        $this->assertCount(2, $plan->details);

        $this->assertNotNull($plan);
        $this->assertEquals('John', $plan->name);
        $this->assertEquals('Bar', $plan->description);
        $this->assertEquals('file', $plan->type);
        $this->assertEquals('none', $plan->state);
        $this->assertNull($plan->run_at);
    }

    /**
     * @test
     */
    public function can_create_plan_only_if_subscribed()
    {
        $this->withNotSubscribedUser();
        $this->withRunningCluster($this->user);

        $this->actingAs($this->user);

        $res = $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
        ]);

        $res->assertRedirect(route('subscription.missing'));
    }

    /**
     * @test
     */
    public function redirect_after_store_plan()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $res = $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
        ]);

        $res->assertRedirect(route('indexing.indexing'));
    }
}
