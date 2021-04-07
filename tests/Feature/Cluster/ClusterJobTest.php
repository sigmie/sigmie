<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use App\Helpers\ClusterManagerFactory;
use App\Jobs\Cluster\ClusterJob;
use App\Jobs\Cluster\UpdateClusterAllowedIps;
use App\Jobs\Cluster\UpdateClusterBasicAuth;
use App\Models\AllowedIp;
use App\Services\Dispatcher;
use Composer\Command\DiagnoseCommand;
use Exception;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\InvalidArgumentException;
use PHPUnit\Framework\MockObject\ClassAlreadyExistsException;
use PHPUnit\Framework\MockObject\ClassIsFinalException;
use PHPUnit\Framework\MockObject\DuplicateMethodException;
use PHPUnit\Framework\MockObject\InvalidMethodNameException;
use PHPUnit\Framework\MockObject\OriginalConstructorInvocationRequiredException;
use PHPUnit\Framework\MockObject\ReflectionException;
use PHPUnit\Framework\MockObject\RuntimeException;
use PHPUnit\Framework\MockObject\UnknownClassException;
use PHPUnit\Framework\MockObject\UnknownTypeException;
use SebastianBergmann\RecursionContext\InvalidArgumentException as RecursionContextInvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class ClusterJobTest extends TestCase
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
     * @var Lock|MockObject
     */
    private $lockMock;

    public function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Event::fake();

        $this->clusterManagerFactoryMock = $this->createMock(ClusterManagerFactory::class);
        $this->lockProviderMock = $this->createMock(LockProvider::class);
        $this->lockMock = $this->createMock(Lock::class);

        $this->lockProviderMock->method('lock')->willReturn($this->lockMock);
    }

    /**
     * @test
     */
    public function handle_job_if_locked()
    {
        $this->withRunningInternalCluster();

        /** @var  MockObject|ClusterJob $job */
        $job = $this->getMockForAbstractClass(ClusterJob::class, [$this->cluster->id]);
        $job->lockAction();

        $this->lockMock->method('get')->willReturn(true);

        $this->lockProviderMock->expects($this->once())->method('lock')->with(ClusterJob::class . '_' . $this->cluster->id);
        $job->expects($this->once())->method('handleJob')->with($this->clusterManagerFactoryMock);
        $this->lockMock->expects($this->once())->method('release');

        $job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        $this->assertFalse($job->isLocked());
        Bus::assertNotDispatched($job::class);
    }

    /**
     * @test
     */
    public function redispatch_job_if_can_not_lock()
    {
        $this->withRunningInternalCluster();

        $job = new UpdateClusterBasicAuth($this->cluster->id);
        $job->lockAction();
        $lockOwner = $job->lockOwner;

        $this->lockMock->method('get')->willReturn(false);

        $this->lockProviderMock->expects($this->once())->method('lock')->with(ClusterJob::class . '_' . $this->cluster->id);

        $job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);

        Bus::assertDispatched(UpdateClusterBasicAuth::class, function (UpdateClusterBasicAuth $job) use ($lockOwner) {
            return ($job->clusterId === $this->cluster->id) && $job->lockOwner === $lockOwner;
        });
    }

    /**
     * @test
     */
    public function action_locking()
    {
        $this->withRunningInternalCluster();

        $job = new UpdateClusterBasicAuth($this->cluster->id);
        $job->lockAction();

        $this->assertTrue($job->isLocked());

        $job->releaseAction();

        $this->assertFalse($job->isLocked());
    }

    /**
     * @test
     */
    public function custom_dispatcher()
    {
        $dispatcher = app(\Illuminate\Bus\Dispatcher::class);

        $this->assertInstanceOf(\App\Services\Dispatcher::class, $dispatcher);
    }

    /**
     * @test
     */
    public function lock_is_released_event_if_method_throws_an_exception()
    {
        $this->withRunningInternalCluster();

        $this->lockMock->method('get')->willReturn('true');
        $job = $this->getMockForAbstractClass(ClusterJob::class, [$this->cluster->id]);
        $job->lockAction();

        $job->method('handleJob')->willThrowException(new Exception('Something went wrong!'));

        $this->assertTrue($job->isLocked());

        try {
            $job->handle($this->clusterManagerFactoryMock, $this->lockProviderMock);
        } catch (Exception) {
        } finally {
            $this->assertFalse($job->isLocked());
        }
    }
}
