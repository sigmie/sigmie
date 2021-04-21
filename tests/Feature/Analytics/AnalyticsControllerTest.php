<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase as TestsTestCase;

class AnalyticsControllerTest extends TestsTestCase
{
    use WithRunningInternalCluster, WithDestroyedCluster, WithProject;

    /**
     * @test
     */
    public function redirect_to_cluster_create_without_cluster()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $res = $this->get(route('analytics.analytics', ['project' => $this->project->id]));

        $res->assertRedirect(route('cluster.create'));
    }

    /**
     * @test
     */
    public function analytics_route_redirects_with_project()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('analytics.analytics'));

        $res->assertRedirect(route('analytics.analytics', ['project' => $this->project->id]));
    }

    /**
     * @test
     */
    public function analytics_route()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('analytics.analytics', ['project' => $this->project->id]));

        $res->assertInertia('analytics/analytics');
    }
}
