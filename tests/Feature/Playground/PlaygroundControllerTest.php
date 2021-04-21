<?php

declare(strict_types=1);

namespace Tests\Feature\Playground;

use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase as TestsTestCase;

class PlaygroundControllerTest extends TestsTestCase
{
    use WithRunningInternalCluster, WithDestroyedCluster, WithProject;

    /**
     * @test
     */
    public function redirect_to_cluster_create_without_cluster()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $res = $this->get(route('playground.playground', ['project' => $this->project->id]));

        $res->assertRedirect(route('cluster.create'));
    }

    /**
     * @test
     */
    public function playground_route_redirects_with_project()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('playground.playground'));

        $res->assertRedirect(route('playground.playground', ['project' => $this->project->id]));
    }

    /**
     * @test
     */
    public function playground_route()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('playground.playground', ['project' => $this->project->id]));

        $res->assertInertia('playground/playground');
    }
}
