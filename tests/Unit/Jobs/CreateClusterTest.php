<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\CreateCluster;
use App\Models\Cluster;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class CreateClusterTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @var CreateCluster
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

    /**
     * @var LockProvider|MockObject
     */
    private $lockProviderMock;

    /**
     * @var Lock|MockObject
     */
    private $lockMock;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->withRunningInternalCluster();

        $this->lockProviderMock = $this->createMock(LockProvider::class);
        $this->lockMock = $this->createMock(Lock::class);
        $this->lockMock->method('get')->willReturn(true);

        $this->lockProviderMock->method('lock')->willReturn($this->lockMock);

        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->clusterManagerMock = $this->createMock(ClusterManager::class);

        $this->clusterManagerFactoryMock->method('create')->willReturn($this->clusterManagerMock);

        $this->job = new CreateCluster($this->cluster->id);
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
    public function handle_triggers_cluster_was_created_event()
    {
        $this->job->lockAction();
        $this->job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        Event::assertDispatched(fn (\App\Events\Cluster\ClusterWasCreated $event) => $event->projectId === $this->project->id);
    }

    /**
     * @test
     */
    public function handle_updates_cluster_state_to_created()
    {
        $this->clusterManagerMock->method('create')->willReturn(['some' => 'design']);
        $this->cluster->update(['state' => Cluster::DESTROYED]);

        $this->job->lockAction();
        $this->job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        $this->cluster->refresh();

        $this->assertEquals($this->cluster->state, Cluster::CREATED);
        $this->assertEquals($this->cluster->design, ['some' => 'design']);
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
    public function handle_calls_cluster_manager_crete_with_core_cluster_instance()
    {
        $this->clusterManagerMock->expects($this->once())->method('create')->with($this->isInstanceOf(CoreCluster::class));

        $this->job->lockAction();
        $this->job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);
    }
}
