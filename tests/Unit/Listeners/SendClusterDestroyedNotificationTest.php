<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Cluster\ClusterWasDestroyed;
use App\Listeners\Notifications\SendClusterDestroyedNotification;
use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\Cluster\ClusterWasDestroyed as ClusterWasDestroyedNotification;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithNotifiableMock;

class SendClusterDestroyedNotificationTest extends TestCase
{
    use WithNotifiableMock;

    /**
     * @var SendClusterDestroyedNotification
     */
    private $listener;

    /**
     * @var MockObject|ClusterWasDestroyed
     */
    private $eventMock;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var integer
     */
    private $clusterId = 0;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var string
     */
    private $projectName = 'foo';

    public function setUp(): void
    {
        parent::setUp();

        $this->withNotifiableMock();

        $this->eventMock = $this->createMock(ClusterWasDestroyed::class);
        $this->eventMock->projectId = $this->clusterId;

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([['name', $this->projectName]]);

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturnMap([['project', $this->projectMock]]);
        $this->clusterMock->method('findUser')->willReturn($this->notifiableMock);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterRepositoryMock->method('findTrashed')->willReturn($this->clusterMock);

        $this->listener = new SendClusterDestroyedNotification($this->clusterRepositoryMock);
    }

    /**
     * @test
     */
    public function handle_finds_trashed_cluster_and_notifies_cluster_user()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('findTrashed')->with($this->clusterId);
        $this->notifiableMock->expects($this->once())->method('notify')->with(new ClusterWasDestroyedNotification($this->projectName));

        $this->listener->handle($this->eventMock);
    }
}
