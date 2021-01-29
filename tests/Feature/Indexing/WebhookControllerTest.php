<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Jobs\Indexing\ExecuteIndexingPlan;
use Illuminate\Support\Facades\Queue;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use WithRunningCluster, WithNotSubscribedUser, WithIndexingPlan;

    /**
     * @test
     */
    public function webhook_is_publicly_accessible()
    {
        $this->withIndexingPlan();

        $url = $this->indexingPlan->webhook_url;

        $this->get($url)->assertOk();
        $this->assertTrue($this->user->isSubscribed());
    }

    /**
     * @test
     */
    public function webhook_returns_unauthorized_if_user_is_not_subscribed()
    {
        $this->withNotSubscribedUser();

        $this->withIndexingPlan(
            user: $this->user
        );

        $url = $this->indexingPlan->webhook_url;

        $this->get($url)->assertUnauthorized();
    }

    /**
    * @test
    */
    public function plan_execute_job_is_dispatched()
    {
        Queue::fake();

        $this->withIndexingPlan();

        $url = $this->indexingPlan->webhook_url;

        $this->get($url);

        Queue::assertPushed(fn (ExecuteIndexingPlan $job) => $this->indexingPlan->id === $job->planId);
    }
}
