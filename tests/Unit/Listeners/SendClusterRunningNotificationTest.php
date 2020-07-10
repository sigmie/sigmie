<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\ClusterWasBooted;
use App\Events\ClusterWasDestroyed;
use App\Listeners\SendClusterRunningNotification;
use App\Models\Cluster;
use App\Models\Project;
use App\Notifications\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsNotifiable;

class SendClusterRunningNotificationTest extends TestCase
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
    private $clusterName = 'bar';

    /**
     * @var string
     */
    private $projectName = 'foo';

    public function setUp(): void
    {
        parent::setUp();

        $this->notifiableMock = $this->notifiable();

        $this->eventMock = $this->createMock(ClusterWasBooted::class);
        $this->eventMock->clusterId = $this->clusterId;

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([['name', $this->projectName]]);

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturnMap([['user', $this->notifiableMock], ['project', $this->projectMock], ['name', $this->clusterName]]);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterRepositoryMock->method('find')->willReturn($this->clusterMock);

        $this->listener = new SendClusterRunningNotification();
    }

    /**
     * @test
     */
    public function handle_finds_trashed_cluster_and_notifies_cluster_user()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('find')->with($this->clusterId);
        $this->notifiableMock->expects($this->once())->method('notify')->with(new ClusterIsRunning($this->clusterName, $this->projectName));

        $this->listener->handle($this->eventMock, $this->clusterRepositoryMock);
    }
}
