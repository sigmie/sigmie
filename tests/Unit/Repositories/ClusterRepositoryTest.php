<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Cluster;
use App\Models\Model;
use App\Repositories\ClusterRepository;
use PHPUnit\Framework\MockObject\MockObject;
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
}
