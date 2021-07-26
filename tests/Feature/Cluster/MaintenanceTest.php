<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Jobs\Cluster\Maintenance;
use App\Jobs\Cluster\RefreshCloudflareIps;
use App\Listeners\Cluster\PollClusterState;
use Illuminate\Support\Facades\Bus;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use WithRunningInternalCluster, WithDestroyedCluster;

    /**
     * @var PollClusterState
     */
    private $listener;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    /**
     * @test
     */
    public function jobs_are_dispatched()
    {
        $this->withRunningInternalCluster();

        $job = new Maintenance;

        $job->handle();

        Bus::assertDispatched(RefreshCloudflareIps::class, function (RefreshCloudflareIps $job) {
            return $job->clusterId === $this->cluster->id;
        });
    }

    /**
     * @test
     */
    public function jobs_are_not_dispatched_on_destroyed_clusters()
    {
        $this->withRunningInternalCluster();

        $runningCluster = $this->cluster;

        $this->withDestroyedCluster();

        $destroyedCluster = $this->cluster;

        $job = new Maintenance;

        $job->handle();

        Bus::assertDispatched(
            RefreshCloudflareIps::class,
            function (RefreshCloudflareIps $job) use ($runningCluster) {
                return $job->clusterId === $runningCluster->id;
            }
        );

        Bus::assertNotDispatched(
            RefreshCloudflareIps::class,
            function (RefreshCloudflareIps $job) use ($destroyedCluster) {
                return $job->clusterId === $destroyedCluster->id;
            }
        );
    }
}
