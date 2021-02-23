<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Enums\PlanTriggers;
use App\Jobs\Indexing\IndexAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class PingControllerTest extends TestCase
{
    use WithRunningCluster, WithNotSubscribedUser, WithIndexingPlan;

    /**
     * @test
     */
    public function ping_url_is_publicly_accessible()
    {
        $this->withIndexingPlan();

        $url = $this->indexingPlan->ping_url;

        $this->get($url)->ray()->assertOk();
        $this->assertTrue($this->user->isSubscribed());
    }

    /**
     * @test
     */
    public function ping_returns_unauthorized_if_user_is_not_subscribed()
    {
        $this->withNotSubscribedUser();

        $this->withIndexingPlan(
            user: $this->user
        );

        $url = $this->indexingPlan->ping_url;

        $this->get($url)->assertUnauthorized();
    }

    /**
     * @test
     */
    public function plan_cant_be_dispatched_if_not_active()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $this->indexingPlan->setAttribute('deactivated_at', Carbon::now())->save();

        $url = $this->indexingPlan->ping_url;

        $this->get($url);

        Queue::assertNotPushed(IndexAction::class);
    }

    /**
     * @test
     */
    public function plan_execute_job_is_dispatched()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $url = $this->indexingPlan->ping_url;

        $this->get($url);

        Queue::assertPushed(fn (IndexAction $job) => $this->indexingPlan->id === $job->planId);

        $this->assertDatabaseHas('indexing_activities', [
            'plan_id' => $this->indexingPlan->id,
            'project_id' => $this->project->id,
            'trigger' => (string)PlanTriggers::PING(),
        ]);
    }

    /**
     * @test
     */
    public function plan_state_is_running()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $url = $this->indexingPlan->ping_url;

        $this->get($url);

        $this->indexingPlan->refresh();

        $this->assertEquals('running', $this->indexingPlan->state);
    }
}
