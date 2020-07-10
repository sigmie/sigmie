<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\ClusterWasDestroyed;
use App\Listeners\SendClusterDestroyedNotification;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Notifications\Notifiable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsNotifiable;
use App\Notifications\ClusterWasDestroyed as ClusterWasDestroyedNotification;

class SendClusterDestroyedNotificationTest extends TestCase
{
    use NeedsNotifiable;

    /**
     * @var SendClusterDestroyedNotification
     */
    private $listener;

    /**
     * @var ClusterWasDestroyed|MockObject
     */
    private $event;

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
     * @var MockObject|Notifiable
     */
    private $notifiableMock;

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

        $this->eventMock = $this->createMock(ClusterWasDestroyed::class);
        $this->eventMock->clusterId = $this->clusterId;
        $this->notifiableMock = $this->notifiable();

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturn($this->projectName);

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturn($this->notifiableMock, $this->projectMock);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterRepositoryMock->method('findTrashed')->willReturn($this->clusterMock);

        $this->listener = new SendClusterDestroyedNotification();
    }

    /**
     * @test
     */
    public function handle_finds_trashed_cluster_and_notifies_cluster_user()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('findTrashed')->with($this->clusterId);
        $this->notifiableMock->expects($this->once())->method('notify')->with(new ClusterWasDestroyedNotification($this->projectName));

        $this->listener->handle($this->eventMock, $this->clusterRepositoryMock);
    }
}
