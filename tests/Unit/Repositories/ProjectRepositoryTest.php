<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Project;
use App\Repositories\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithModelMock;

class ProjectRepositoryTest extends TestCase
{
    use WithModelMock;

    /**
     * @var ProjectRepository
     */
    private $repository;

    /**
     * @var Project|MockObject
     */
    private $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = $this->withModelMock(Project::class);

        $this->repository = new ProjectRepository($this->model);
    }

    /**
     * @test
     */
    public function can_be_instantiated(): void
    {
        $this->assertInstanceOf(ProjectRepository::class, $this->repository);
    }
}
