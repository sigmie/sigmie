<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\Cluster\ClusterWasBooted;
use App\Listeners\Notifications\SendClusterRunningNotification;
use App\Models\Cluster;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Cluster\ClusterIsRunning;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithNotifiableMock;

class SendClusterRunningNotificationTest extends TestCase
{
    use WithNotifiableMock;

    /**
     * @var SendClusterRunningNotification
     */
    private $listener;

    /**
     * @var MockObject|ClusterWasBooted
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
     * @var MockObject|User
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

        $this->withNotifiableMock();

        $this->eventMock = $this->createMock(ClusterWasBooted::class);
        $this->eventMock->projectId = $this->clusterId;

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([['name', $this->projectName]]);

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturnMap([['project', $this->projectMock], ['name', $this->clusterName]]);
        $this->clusterMock->method('findUser')->willReturn($this->notifiableMock);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);

        $this->listener = new SendClusterRunningNotification($this->clusterRepositoryMock);
    }

    /**
     * @test
     */
    public function handle_finds_trashed_cluster_and_notifies_cluster_user()
    {
        $this->clusterRepositoryMock->expects($this->once())->method('find')->with($this->clusterId)->willReturn($this->clusterMock);
        $this->notifiableMock->expects($this->once())->method('notify')->with(new ClusterIsRunning($this->clusterName, $this->projectName));

        $this->listener->handle($this->eventMock);
    }
}
