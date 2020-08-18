<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Cluster;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsModel;

class ClusterRepositoryTest extends TestCase
{
    use NeedsModel;

    /**
     * @var ClusterRepository
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new ClusterRepository($this->model(Cluster::class));
    }

    /**
     * @test
     */
    public function with_trashed()
    {
        $this->model()->method('withTrashed')->willReturnSelf();
        $this->model()->method('where')->willReturnSelf();
        $this->model()->method('first')->willReturn(new Cluster(['prop' => 'value']));

        $this->model()->expects($this->once())->method('withTrashed');
        $this->model()->expects($this->once())->method('where')->with('id', 123);
        $this->model()->expects($this->once())->method('first');

        $this->assertEquals(new Cluster(['prop' => 'value']), $this->repository->findTrashed(123));
    }

    /**
     * @test
     */
    public function restore()
    {
        $this->model()->method('withTrashed')->willReturnSelf();
        $this->model()->method('firstWhere')->willReturnSelf();
        $this->model()->method('restore')->willReturn(true);

        $this->model()->expects($this->once())->method('firstWhere')->with('id', 1);
        $this->model()->expects($this->once())->method('restore');

        $this->assertTrue($this->repository->restore(1));
    }

    /**
     * @test
     */
    public function update_trashed()
    {
        $this->model()->method('withTrashed')->willReturnSelf();
        $this->model()->method('where')->willReturnSelf();
        $this->model()->method('first')->willReturnSelf();
        $this->model()->method('update')->willReturn(true);

        $this->model()->expects($this->once())->method('withTrashed');
        $this->model()->expects($this->once())->method('where')->with('id', 1);
        $this->model()->expects($this->once())->method('first');
        $this->model()->expects($this->once())->method('update')->with(['foo' => 'bar']);

        $this->assertTrue($this->repository->updateTrashed(1, ['foo' => 'bar']));
    }
}
