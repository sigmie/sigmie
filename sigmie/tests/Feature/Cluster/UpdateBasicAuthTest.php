<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\UpdateClusterBasicAuth;
use App\Notifications\Cluster\ClusterBasicAuthWasUpdated;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Contracts\ClusterManager;
use Sigmie\App\Core\Software\Update;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class UpdateBasicAuthTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @var ClusterManagerFactory|MockObject
     */
    private $clusterManagerFactoryMock;

    /**
     * @var LockProvider|MockObject
     */
    private $lockProviderMock;

    /**
     * @var ClusterManager|MockObject
     */
    private $clusterManagerMock;

    /**
     * @var Lock|MockObject
     */
    private $lockMock;

    /**
     * @var MockObject|Update
     */
    private $updateMock;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Event::fake();
        Notification::fake();

        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->lockProviderMock = $this->createMock(LockProvider::class);
        $this->lockMock = $this->createMock(Lock::class);
        $this->clusterManagerMock = $this->createMock(ClusterManager::class);
        $this->updateMock = $this->createMock(Update::class);

        $this->clusterManagerFactoryMock->method('create')->willReturn($this->clusterManagerMock);
        $this->clusterManagerMock->method('update')->willReturn($this->updateMock);

        $this->lockProviderMock->method('lock')->willReturn($this->lockMock);
        $this->lockMock->method('get')->willReturn(true);
    }

    /**
     * @test
     */
    public function update_cluster_basic_auth_and_fire_event_and_notify_the_user()
    {
        $this->withRunningInternalCluster();

        $job = new UpdateClusterBasicAuth($this->cluster->id);
        $job->lockAction();

        $this->updateMock->expects($this->once())->method('basicAuth')->with($this->cluster->username, decrypt($this->cluster->password));

        $job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId === $this->project->id;
        });

        Notification::assertSentTo($this->user, ClusterBasicAuthWasUpdated::class, function (ClusterBasicAuthWasUpdated $notification) {
            return $notification->projectName === $this->project->name;
        });
    }
}
