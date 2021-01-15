<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\BaseRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithModelMock;

class BaseRepositoryTest extends TestCase
{
    use WithModelMock;

    /**
     * @var BaseRepository
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new class ($this->withModelMock()) extends BaseRepository
        {
        };
    }

    /**
     * @test
     */
    public function find(): void
    {
        $this->withModelMock()->method('find')->willReturnSelf();
        $this->withModelMock()->expects($this->once())->method('find')->with(0);

        $this->repository->find(0);
    }

    /**
     * @test
     */
    public function update(): void
    {
        $this->withModelMock()->method('find')->willReturnSelf();
        $this->withModelMock()->method('update')->willReturn(true);
        $this->withModelMock()->expects($this->once())->method('find')->with(1);
        $this->withModelMock()->expects($this->once())->method('update')->with(['column' => 'value']);

        $this->assertTrue($this->repository->update(1, ['column' => 'value']));
    }

    /**
     * @test
     */
    public function delete(): void
    {
        $this->withModelMock()->method('find')->willReturnSelf();
        $this->withModelMock()->method('delete')->willReturn(true);
        $this->withModelMock()->expects($this->once())->method('find')->with(1);
        $this->withModelMock()->expects($this->once())->method('delete')->with();

        $this->assertTrue($this->repository->delete(1));
    }

    /**
     * @test
     */
    public function find_one_by()
    {
        $this->withModelMock()->method('firstWhere')->willReturn(null);
        $this->withModelMock()->expects($this->once())->method('firstWhere')->with('foo', 'bar');

        $result = $this->repository->findOneBy('foo', 'bar');
        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function create()
    {
        $this->withModelMock()->method('create')->willReturnSelf();
        $this->withModelMock()->expects($this->once())->method('create')->with(['foo' => 'bar']);

        $this->repository->create(['foo' => 'bar']);
    }
}
