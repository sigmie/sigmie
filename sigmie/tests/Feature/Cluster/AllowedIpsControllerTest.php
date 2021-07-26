<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Models\AllowedIp;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class AllowedIpsControllerTest extends TestCase
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
    public function update_action_doesnt_dispatch_when_only_name_changed()
    {
        $this->withRunningInternalCluster();

        $this->cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);

        $address =  $this->cluster->allowedIps->first();

        $this->assertInstanceOf(AllowedIp::class, $address);

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.update', ['cluster' => $this->cluster->id, $address->id]);

        $res = $this->put($route, ['name' => 'new name', 'ip' => '192.0.0.1']);

        $res->assertSessionHasNoErrors();

        Bus::assertNotDispatched(UpdateClusterAllowedIps::class);

        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId = $this->project->id;
        });
    }

    public function update_action()
    {
        $this->withRunningInternalCluster();

        $this->cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);

        $address =  $this->cluster->allowedIps->first();

        $this->assertInstanceOf(AllowedIp::class, $address);

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.update', ['cluster' => $this->cluster->id, $address->id]);

        $res = $this->put($route, ['name' => 'new name', 'ip' => '10.0.0.1']);


        $res->assertSessionHasNoErrors();
        $res->assertRedirect(route('settings'));

        Bus::assertDispatched(UpdateClusterAllowedIps::class, function (UpdateClusterAllowedIps $job) {
            return $job->clusterId === $this->cluster->id;
        });

        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId = $this->project->id;
        });

        $this->cluster->refresh();

        $address =  $this->cluster->allowedIps->first();

        $this->assertEquals('name name', $address->name);
        $this->assertEquals('10.0.0.1', $address->ip);
    }

    /**
     * @test
     */
    public function update_action_is_authorized()
    {
        $this->withRunningInternalCluster();

        $cluster = $this->cluster;
        $cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);
        $address = $cluster->allowedIps->first();

        $this->assertInstanceOf(AllowedIp::class, $address);

        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.update', ['cluster' => $cluster->id, $address->id]);

        $res = $this->put($route, ['name' => 'some name', 'ip' => '10.0.0.0']);

        $res->assertForbidden();
    }

    /**
     * @test
     */
    public function destroy_request_is_authorized(): void
    {
        $this->withRunningInternalCluster();

        $cluster = $this->cluster;
        $cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);
        $address = $cluster->allowedIps->first();

        $this->assertInstanceOf(AllowedIp::class, $address);

        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.destroy', ['cluster' => $cluster->id, $address->id]);

        $res = $this->delete($route);


        $res->assertForbidden();
    }

    /**
     * @test
     */
    public function destroy_action()
    {
        $this->withRunningInternalCluster();

        $this->cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);

        $address =  $this->cluster->allowedIps->first();

        $this->assertInstanceOf(AllowedIp::class, $address);

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.destroy', ['cluster' => $this->cluster->id, $address->id]);

        $res = $this->delete($route);

        $this->cluster->refresh();

        $res->assertRedirect(route('settings'));

        Bus::assertDispatched(UpdateClusterAllowedIps::class, function (UpdateClusterAllowedIps $job) {
            return $job->clusterId === $this->cluster->id;
        });
        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId = $this->project->id;
        });

        $this->assertNull($this->cluster->allowedIps->first());
    }


    /**
     * @test
     */
    public function store_works_with_valid_data(): void
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.store', ['cluster' => $this->cluster->id]);

        $res = $this->post($route, ['name' => 'fooo', 'ip' => '192.159.0.1']);

        $res->assertSessionHasNoErrors();

        Bus::assertDispatched(UpdateClusterAllowedIps::class, function (UpdateClusterAllowedIps $job) {
            return $job->clusterId === $this->cluster->id;
        });
        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId = $this->project->id;
        });

        $res->assertRedirect(route('settings'));

        $this->cluster->refresh();

        $ip = $this->cluster->allowedIps->first();

        $this->assertEquals('fooo', $ip->name);
        $this->assertEquals('192.159.0.1', $ip->ip);
    }

    /**
     * @test
     */
    public function store_request_is_authorized(): void
    {
        $this->withRunningInternalCluster();

        $cluster = $this->cluster;

        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.store', ['cluster' => $cluster->id]);

        $res = $this->post($route, ['name' => 'fooo', 'ip' => '192.159.0.1']);

        $res->assertForbidden();
    }

    /**
     * @test
     */
    public function ip_should_be_unique_per_cluster(): void
    {
        $this->withRunningInternalCluster();

        $this->actingAs($this->user);

        $route = route('cluster.allowed-ips.store', ['cluster' => $this->cluster->id]);

        $res = $this->post($route, ['name' => 'fooo', 'ip' => '192.159.0.1']);

        $res->assertSessionHasNoErrors();

        $res = $this->post($route, ['name' => 'fooo', 'ip' => '192.159.0.1']);

        $res->assertSessionHasErrors();
    }
}
