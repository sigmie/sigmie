<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Jobs\Cluster\UpdateClusterAllowedIps;
use Illuminate\Support\Facades\Bus;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class AllowedIpsControllerTest extends TestCase
{
    use WithRunningInternalCluster;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
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
