<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use WithProject, WithRunningExternalCluster, WithDestroyedCluster;

    /**
     * @test
     */
    public function cluster_is_null_when_cluster_is_destroyed()
    {
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $response = $this->get(route('settings', ['project' => $this->project->id]));

        $this->assertNull($response->inertiaProps('cluster'));
    }

    /**
     * @test
     */
    public function index_has_cluster_state()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $response = $this->get(route('settings', ['project' => $this->project->id]));

        $this->assertNotNull($this->cluster);

        $response->assertInertiaHas('cluster', [
            'id' => $this->cluster->id,
            'state' => $this->cluster->state,
            'has_allowed_ips' => false,
            'can_be_destroyed' => false,
            'type' => $this->cluster->getMorphClass()
        ]);
    }

    /**
     * @test
     */
    public function index_has_project_name_and_desc()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $response = $this->get(route('settings', ['project' => $this->project->id]));

        $response->assertOk();

        $response->assertInertiaHas('cluster', null);
        $response->assertInertiaHas(
            'project',
            [
                'name' => $this->project->name,
                'description' => $this->project->description,
                'id' => $this->project->id
            ]
        );
    }
}
