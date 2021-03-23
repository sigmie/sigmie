<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Listeners\Cluster\UpdateClusterStateToError;
use App\Models\Cluster;
use App\Policies\ClusterPolicy;
use App\Repositories\ClusterRepository;
use Tests\Helpers\WithRunningCluster;
use Tests\Helpers\WithSubscribedUser;
use Tests\TestCase;

class ClusterPolicyTest extends TestCase
{
    use WithRunningCluster, WithSubscribedUser;

    /**
     * @test
     */
    public function not_allowed_actions()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $policy = new ClusterPolicy;

        $this->assertFalse($policy->viewAny($this->user));
        $this->assertFalse($policy->view($this->user, $this->cluster));
        $this->assertFalse($policy->forceDelete($this->user, $this->cluster));
    }

    /**
     * @test
     */
    public function deleteed()
    {
        $policy = new ClusterPolicy;

        $this->withSubscribedUser();

        $user = $this->user;

        $this->withRunningCluster();

        $this->assertFalse($policy->restore($user, $this->cluster));
        $this->assertFalse($policy->update($user, $this->cluster));
        $this->assertFalse($policy->delete($user, $this->cluster));

        $this->cluster->update(['state' => Cluster::DESTROYED]);

        $this->assertTrue($policy->restore($this->user, $this->cluster));
        $this->assertTrue($policy->update($this->user, $this->cluster));
        $this->assertTrue($policy->delete($this->user, $this->cluster));
    }
}
