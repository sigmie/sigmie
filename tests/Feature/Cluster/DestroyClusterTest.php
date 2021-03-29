<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\DestroyCluster;
use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;
use Tests\Helpers\WithClusterMock;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class DestroyClusterTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @var DestroyCluster
     */
    private $job;

    /**
     * @var ClusterManagerFactory|MockObject
     */
    private $clusterManagerFactoryMock;

    /**
     * @var ClusterManager|MockObject
     */
    private $clusterManagerMock;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->clusterManagerMock = $this->createMock(ClusterManager::class);

        $this->clusterManagerFactoryMock->method('create')->willReturn($this->clusterManagerMock);

        $this->withRunningInternalCluster();

        $this->job = new DestroyCluster($this->cluster->id);
    }

    /**
     * @test
     */
    public function job_is_handled_in_queue()
    {
        $this->assertInstanceOf(ShouldQueue::class, $this->job);
    }

    /**
     * @test
     */
    public function handle_triggers_cluster_event()
    {
        $this->job->handle($this->clusterManagerFactoryMock);

        Event::assertDispatched(fn (\App\Events\Cluster\ClusterWasDestroyed $event) => $event->projectId === $this->project->id);
    }

    /**
     * @test
     */
    public function handle_updates_cluster_state()
    {
        $this->cluster->update(['design' => ['some' => 'design']]);

        $this->assertEquals(['some' => 'design'], $this->cluster->design);

        $this->job->handle($this->clusterManagerFactoryMock);

        $this->cluster->refresh();

        $this->assertEquals(Cluster::DESTROYED, $this->cluster->state);
        $this->assertEquals([], $this->cluster->design);
    }

    /**
     * @test
     */
    public function handle_removes_ip_addresses()
    {
        $this->cluster
            ->allowedIps()
            ->create(
                ['name' => 'foo', 'ip' => '192.0.0.1']
            );

        $this->assertTrue($this->cluster->allowedIps->isNotEmpty());

        $this->job->handle($this->clusterManagerFactoryMock);

        $this->cluster->refresh();

        $this->assertTrue($this->cluster->allowedIps->isEmpty());

        $this->assertEquals(Cluster::DESTROYED, $this->cluster->state);
    }

    /**
     * @test
     */
    public function is_in_long_queue()
    {
        $this->assertEquals('long-running-queue', $this->job->queue);
    }

    /**
     * @test
     */
    public function handle_calls_cluster_manager_destroy_with_core_cluster_instance()
    {
        $this->clusterManagerMock->expects($this->once())->method('destroy')->with($this->isInstanceOf(CoreCluster::class));

        $this->job->handle($this->clusterManagerFactoryMock);
    }
}
