<?php

namespace Tests\Unit;

use App\Events\Cluster\ClusterHasFailed;
use App\Listeners\Cluster\UpdateClusterStateToError;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class UpdateClusterStateToErrorTest extends TestCase
{
    /**
     * @var UpdateClusterStateToError
     */
    private $listener;

    /**
     * @var ClusterRepository|MockObject
     */
    private $repositoryMock;


    public function setUp(): void
    {
        $this->repositoryMock = $this->createMock(ClusterRepository::class);

        $this->listener = new UpdateClusterStateToError($this->repositoryMock);
    }

    /**
     * @test
     */
    public function handle_sets_cluster_state_to_error()
    {
        $this->repositoryMock->expects($this->once())->method('update')->with(1, ['state' => 'failed']);

        $this->listener->handle(new ClusterHasFailed(1));
    }
}
