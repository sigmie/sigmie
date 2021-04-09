<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Jobs\Cluster\UpdateClusterBasicAuth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class BasicAuthControllerTest extends TestCase
{
    use WithRunningInternalCluster;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Event::fake();
    }

    /**
     * @test
     */
    public function update_action_is_authorized()
    {
        $this->withRunningInternalCluster();

        $cluster = $this->cluster;

        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.basic-auth.update', ['cluster' => $cluster->id]);

        $res = $this->put($route, ['username' => 'leo', 'password' => '12334']);

        $res->assertForbidden();
    }

    /**
     * @test
     */
    public function update_action()
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.basic-auth.update', ['cluster' => $this->cluster->id]);

        $res = $this->put($route, ['username' => 'leo', 'password' => '12334']);
        $this->cluster->refresh();

        $this->assertEquals('leo', $this->cluster->username);
        $this->assertEquals('12334', decrypt($this->cluster->password));

        $res->assertRedirect(route('settings'));
        $res->assertSessionHasNoErrors();


        Bus::assertDispatched(UpdateClusterBasicAuth::class, function (UpdateClusterBasicAuth $job) {
            return $job->clusterId === $this->cluster->id;
        });

        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId = $this->project->id;
        });
    }
}
