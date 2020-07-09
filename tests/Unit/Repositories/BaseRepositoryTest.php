<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Model;
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
}
