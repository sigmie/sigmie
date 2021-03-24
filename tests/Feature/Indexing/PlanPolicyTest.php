<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Http\Middleware\Redirects\RedirectToRenewSubscriptionIfNotSubscribed;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class PlanPolicyTest extends TestCase
{
    use WithRunningExternalCluster, WithNotSubscribedUser, WithIndexingPlan;

    /**
     * @test
     */
    public function update_policy_checks_user()
    {
        $this->withoutMiddleware(RedirectToRenewSubscriptionIfNotSubscribed::class);

        $this->withIndexingPlan();
        $this->withNotSubscribedUser();

        $this->actingAs($this->user);

        $res = $this->put(route('indexing.plan.update', ['plan' => $this->indexingPlan->id]), [
            'name' => 'John',
        ]);

        $res->assertForbidden();
    }

    /**
     * @test
     */
    public function create_policy_checks_if_user_is_subscribed()
    {
        $this->withoutMiddleware(RedirectToRenewSubscriptionIfNotSubscribed::class);

        $this->withNotSubscribedUser();
        $this->withRunningExternalCluster($this->user);

        $this->actingAs($this->user);

        $res = $this->post(route('indexing.plan.store'), [
            'name' => 'John',
            'description' => 'Bar',
            'cluster_id' => $this->cluster->id,
            'type' => 'file',
        ]);

        $res->assertForbidden();
    }
}
