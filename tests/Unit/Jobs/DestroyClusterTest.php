<?php


declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\DestroyCluster;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;
use Tests\Helpers\WithClusterMock;
use Tests\TestCase;

class DestroyClusterTest extends TestCase
{
    use WithClusterMock;

    /**
     * @var DestroyCluster
     */
    private $job;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

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

        $this->withClusterMock();

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->clusterManagerMock = $this->createMock(ClusterManager::class);

        $this->clusterManagerFactoryMock->method('create')->willReturn($this->clusterManagerMock);
        $this->clusterRepositoryMock->method('findTrashed')->willReturn($this->clusterMock);

        $this->job = new DestroyCluster($this->clusterId);
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
        $this->job->handle($this->clusterRepositoryMock, $this->clusterManagerFactoryMock);

        Event::assertDispatched(fn (\App\Events\Cluster\ClusterWasDestroyed $event) => $event->clusterId === $this->clusterId);
    }

    /**
     * @test
     */
    public function handle_updates_cluster_state_to_created()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('updateTrashed')->with($this->clusterId, ['state' => 'destroyed']);

        $this->job->handle($this->clusterRepositoryMock, $this->clusterManagerFactoryMock);
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
        $this->clusterManagerMock->expects($this->once())->method('destroy')->with($this->isInstanceOf(CoreCluster::class));

        $this->job->handle($this->clusterRepositoryMock, $this->clusterManagerFactoryMock);
    }
}
