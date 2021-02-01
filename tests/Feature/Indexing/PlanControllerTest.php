<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Models\FileType;
use App\Models\IndexingPlan;
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

        $oldPlanId = $this->indexingPlan->type->id;

        $this->put(route('indexing.plan.update', ['plan' => $this->indexingPlan->id]), [
            'name' => 'John',
            'description' => 'Bar',
            'type' => [
                'type' => 'file',
                'index_alias' => 'bar',
                'location' => 'https://github.com'
            ],
        ])->assertSessionHasNoErrors();

        $this->indexingPlan->refresh();

        $this->assertEquals($this->indexingPlan->type->location, 'https://github.com');
        $this->assertEquals('bar', $this->indexingPlan->type->index_alias);
        $this->assertNotEquals($oldPlanId, $this->indexingPlan->type->id);

        $this->assertEquals('John', $this->indexingPlan->name);
        $this->assertEquals('Bar', $this->indexingPlan->description);
    }

    /**
     * @test
     */
    public function create_plan()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $res = $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => [
                'type' => 'file',
                'index_alias' => 'demo_index_0111',
                'location' => 'https://google.com'
            ],
        ]);

        $res->assertSessionHasNoErrors();
        $res->assertRedirect(route('indexing.indexing'));

        $plan = $this->cluster->plans->first();

        $this->assertNotNull($plan);
        $this->assertEquals('John', $plan->name);
        $this->assertEquals('Bar', $plan->description);
        $this->assertInstanceOf(FileType::class, $plan->type);
        $this->assertTrue($plan->type->exists);
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
}
