<?php

declare(strict_types=1);

namespace Tests\Feature\Playground;

use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase as TestsTestCase;

class AnalysisControllerTest extends TestsTestCase
{
    use WithRunningInternalCluster, WithDestroyedCluster, WithProject;

    /**
     * @test
     */
    public function redirect_to_cluster_create_without_cluster()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $res = $this->get(route('analysis.analysis', ['project' => $this->project->id]));

        $res->assertRedirect(route('cluster.create'));
    }

    /**
     * @test
     */
    public function analysis_route_redirects_with_project()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('analysis.analysis'));

        $res->assertRedirect(route('analysis.analysis', ['project' => $this->project->id]));
    }

    /**
     * @test
     */
    public function analysis_route_redirects_with_project_and_custom_query_params()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('analysis.analysis') . '?foo=bar',);

        $res->assertRedirect(route('analysis.analysis', ['project' => $this->project->id]) . '?foo=bar');
    }

    /**
     * @test
     */
    public function analysis_route()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('analysis.analysis', ['project' => $this->project->id]));

        $res->assertInertia('analysis/analysis');
    }
}
