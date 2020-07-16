<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\BaseRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsModel;

class BaseRepositoryTest extends TestCase
{
    use NeedsModel;

    /**
     * @var BaseRepository
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new class ($this->model()) extends BaseRepository
        {
        };
    }

    /**
     * @test
     */
    public function find(): void
    {
        $this->model()->method('find')->willReturnSelf();
        $this->model()->expects($this->once())->method('find')->with(0);

        $this->repository->find(0);
    }

    /**
     * @test
     */
    public function update(): void
    {
        $this->model()->method('find')->willReturnSelf();
        $this->model()->method('update')->willReturn(true);
        $this->model()->expects($this->once())->method('find')->with(1);
        $this->model()->expects($this->once())->method('update')->with(['column' => 'value']);

        $this->assertTrue($this->repository->update(1, ['column' => 'value']));
    }

    /**
     * @test
     */
    public function delete(): void
    {
        $this->model()->method('find')->willReturnSelf();
        $this->model()->method('delete')->willReturn(true);
        $this->model()->expects($this->once())->method('find')->with(1);
        $this->model()->expects($this->once())->method('delete')->with();

        $this->assertTrue($this->repository->delete(1));
    }

    /**
     * @test
     */
    public function create()
    {
        $this->model()->method('create')->willReturnSelf();
        $this->model()->expects($this->once())->method('create')->with(['foo' => 'bar']);

        $this->repository->create(['foo' => 'bar']);
    }
}
