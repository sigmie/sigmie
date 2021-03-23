<?php


declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Models\Region;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithProject;
use Tests\TestCase;

class ClusterControllerTest extends TestCase
{
    use WithProject, WithDestroyedCluster;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    /**
     * @test
     */
    public function update_cluster()
    {
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $res = $this->put(route('cluster.update', ['cluster' => $this->cluster->id]), [
            'region_id' => 1,
            'nodes_count' => 2,
            'username' => 'foo',
            'password' => '1234',
            'memory' => '1024',
            'cores' => '2',
            'disk' => '30'
        ]);

        $res->assertSessionDoesntHaveErrors();
        $res->assertRedirect(route('dashboard'));

        $this->project->refresh();

        $cluster = $this->project->clusters->first();

        $this->assertEquals(Cluster::QUEUED_CREATE, $cluster->state);
        $this->assertEquals(app_core_version(), $cluster->core_version);
    }

    /**
     * @test
     */
    public function store_cluster()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $domain = config('services.cloudflare.domain');

        $res = $this->post(route('cluster.store'), [
            'name' => 'booyah',
            'region_id' => 1,
            'project_id' => $this->project->id,
            'nodes_count' => 2,
            'username' => 'foo',
            'password' => '1234',
            'memory' => '1024',
            'cores' => '2',
            'disk' => '30'
        ]);

        $res->assertSessionDoesntHaveErrors();
        $res->assertRedirect(route('dashboard'));

        $this->project->refresh();

        $cluster = $this->project->clusters->first();

        $this->assertEquals(Cluster::QUEUED_CREATE, $cluster->state);
        $this->assertEquals("https://booyah.{$domain}", $cluster->url);
        $this->assertEquals(app_core_version(), $cluster->core_version);
    }

    /**
     * @test
     */
    public function destroy_cluster()
    {
        $this->withDestroyedCluster();

        $this->actingAs($this->user);

        $response = $this->delete(route('cluster.destroy', ['cluster' => $this->cluster->id]));

        Bus::assertDispatched(function (DestroyCluster $job) {
            return $job->clusterId === $this->cluster->id;
        });

        $response->assertRedirect(route('dashboard'));

        $this->cluster->refresh();

        $this->assertEquals(Cluster::QUEUED_DESTROY, $this->cluster->state);
        $this->assertNotNull($this->cluster->deleted_at);
    }

    /**
     * @test
     */
    public function create_renders_inertia_cluster_create(): void
    {
        $this->withProject();

        $this->actingAs($this->user);

        $this->get(route('cluster.create', ['project_id' => $this->project->id]))
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
