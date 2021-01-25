<?php


declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Models\Region;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\TestCase;

class ClusterControllerTest extends TestCase
{
    use WithProject, WithDestroyedCluster;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function create_renders_inertia_cluster_create(): void
    {
        $this->withProject();

        $this->actingAs($this->user);

        $this->get(route('cluster.create'))
            ->assertInertia('cluster/create/create', ['regions' => Region::all(['id', 'class', 'name'])]);
    }

    /**
     * @test
     */
    public function edit_renders_inertia_cluster_edit_with_cluster_data_arguments()
    {
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $route = route('cluster.edit', ['cluster' => $this->cluster->id]);

        $this->assertInertiaViewExists('cluster/edit/edit');
        $this->get($route)->assertInertia('cluster/edit/edit', [
            'regions' => Region::all(['id', 'class', 'name']),
            'cluster' => [
                'id' => $this->cluster->id,
                'name' => $this->cluster->name
            ]
        ]);
    }
}
