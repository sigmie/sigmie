<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Enums\PlanTriggers;
use App\Jobs\Indexing\IndexAction;
use App\Models\IndexingPlan;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\WithIndexingPlan;
use Tests\TestCase;

class TriggerControllerTest extends TestCase
{
    use WithIndexingPlan;

    /**
     * @test
     */
    public function trigger_action()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $route = route('indexing.plan.trigger', ['plan' => $this->indexingPlan->id]);

        $this->post($route)->assertRedirect();

        Queue::assertPushed(fn (IndexAction $job) => $this->indexingPlan->id === $job->planId);

        $this->assertDatabaseHas('indexing_activities', [
            'plan_id' => $this->indexingPlan->id,
            'project_id' => $this->project->id,
            'trigger' => IndexingPlan::TRIGGER_MANUAL,
        ]);
    }

    /**
     * @test
     */
    public function plan_state_is_running()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $route = route('indexing.plan.trigger', ['plan' => $this->indexingPlan->id]);

        $this->post($route);

        $this->indexingPlan->refresh();

        $this->assertEquals('running', $this->indexingPlan->state);
    }
}
