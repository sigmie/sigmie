<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithModelMock;

class ClusterRepositoryTest extends TestCase
{
    use WithModelMock;

    /**
     * @var ClusterRepository
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new ClusterRepository($this->withModelMock(Cluster::class));
    }

    /**
     * @test
     */
    public function with_trashed()
    {
        $this->withModelMock()->method('withTrashed')->willReturnSelf();
        $this->withModelMock()->method('where')->willReturnSelf();
        $this->withModelMock()->method('first')->willReturn(new Cluster(['prop' => 'value']));

        $this->withModelMock()->expects($this->once())->method('withTrashed');
        $this->withModelMock()->expects($this->once())->method('where')->with('id', 123);
        $this->withModelMock()->expects($this->once())->method('first');

        $this->assertEquals(new Cluster(['prop' => 'value']), $this->repository->findTrashed(123));
    }

    /**
     * @test
     */
    public function restore()
    {
        $this->withModelMock()->method('withTrashed')->willReturnSelf();
        $this->withModelMock()->method('firstWhere')->willReturnSelf();
        $this->withModelMock()->method('restore')->willReturn(true);

        $this->withModelMock()->expects($this->once())->method('firstWhere')->with('id', 1);
        $this->withModelMock()->expects($this->once())->method('restore');

        $this->assertTrue($this->repository->restore(1));
    }

    /**
     * @test
     */
    public function update_trashed()
    {
        $this->withModelMock()->method('withTrashed')->willReturnSelf();
        $this->withModelMock()->method('where')->willReturnSelf();
        $this->withModelMock()->method('first')->willReturnSelf();
        $this->withModelMock()->method('update')->willReturn(true);

        $this->withModelMock()->expects($this->once())->method('withTrashed');
        $this->withModelMock()->expects($this->once())->method('where')->with('id', 1);
        $this->withModelMock()->expects($this->once())->method('first');
        $this->withModelMock()->expects($this->once())->method('update')->with(['foo' => 'bar']);

        $this->assertTrue($this->repository->updateTrashed(1, ['foo' => 'bar']));
    }
}
