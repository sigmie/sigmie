<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Notifications\Cluster\ClusterAllowedIpsWereUpdated;
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

class UpdateAllowedIpsTest extends TestCase
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
        $this->cluster->allowedIps()->create(['name' => 'booyah', 'ip' => '192.0.0.1']);
        $this->cluster->allowedIps()->create(['name' => 'booyah2', 'ip' => '10.0.0.1']);

        $job = new UpdateClusterAllowedIps($this->cluster->id);
        $job->lockAction();

        $this->updateMock->expects($this->once())->method('allowedIps')->with(['192.0.0.1', '10.0.0.1']);

        $job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        Event::assertDispatched(ClusterWasUpdated::class, function (ClusterWasUpdated $event) {
            return $event->projectId === $this->project->id;
        });

        Notification::assertSentTo($this->user, ClusterAllowedIpsWereUpdated::class, function (ClusterAllowedIpsWereUpdated $notification) {
            return $notification->projectName === $this->project->name;
        });
    }
}
