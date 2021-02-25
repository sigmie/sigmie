<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers\Project;

use App\Http\Controllers\Project\SettingsController;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
{
    use WithProject, WithRunningCluster, WithDestroyedCluster;

    /**
     * @test
     */
    public function index_has_cluster_state()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $response = $this->get(route('settings', ['project' => $this->project->id]));

        $this->assertNotNull($this->cluster);

        $response->assertInertiaHas('clusterId', $this->cluster->id);
        $response->assertInertiaHas('clusterState', $this->cluster->state);
    }

    /**
     * @test
     */
    public function index_has_project_name_and_desc()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $response = $this->get(route('settings', ['project' => $this->project->id]));

        $response->assertInertiaHas('clusterId', null);
        $response->assertInertiaHas('clusterState', null);
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
