<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Events\ClusterWasCreated;
use App\Helpers\ClusterManagerFactory;
use App\Jobs\CreateCluster;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cluster as CoreCluster;
use Sigmie\App\Core\Contracts\ClusterManager;
use Tests\TestCase;

class CreateClusterTest extends TestCase
{
    /**
     * @var CreateCluster
     */
    private $job;

    /**
     * @var integer
     */
    private $clusterId = 0;

    /**
     * @var integer
     */
    private $projectId = -1;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

    /**
     * @var ClusterManagerFactory|MockObject
     */
    private $clusterManagerFactoryMock;

    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var ClusterManager|MockObject
     */
    private $clusterManagerMock;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->projectMock = $this->createMock(Project::class);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->clusterManagerMock = $this->createMock(ClusterManager::class);

        $this->clusterManagerFactoryMock->method('create')->willReturn($this->clusterManagerMock);
        $this->clusterRepositoryMock->method('findTrashed')->willReturn($this->clusterMock);

        $clusterAttributes = [
            ['project', $this->projectMock],
            ['id', $this->clusterId],
            ['data_center', 'europe'],
            ['name', 'foo'],
            ['username', 'bar'],
            ['password', encrypt('baz')],
            ['nodes_count', 3],
        ];

        $this->clusterMock->method('getAttribute')->willReturnMap($clusterAttributes);
        $this->projectMock->method('getAttribute')->willReturnMap([['id', $this->projectId]]);

        $this->job = new CreateCluster($this->clusterId);
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

        Event::assertDispatched(fn (ClusterWasCreated $event) => $event->clusterId === $this->clusterId);
    }

    /**
     * @test
     */
    public function handle_updates_cluster_state_to_created()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('update')->with($this->clusterId, ['state' => 'created']);

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
        $this->clusterManagerMock->expects($this->once())->method('create')->with($this->isInstanceOf(CoreCluster::class));

        $this->job->handle($this->clusterRepositoryMock, $this->clusterManagerFactoryMock);
    }
}
