<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Cluster;
use App\Models\Model;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use App\Repositories\ProjectRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsModel;

class ProjectRepositoryTest extends TestCase
{
    use NeedsModel;

    /**
     * @var ClusterRepository
     */
    private $repository;

    public function setUp(): void
    {
        parent::setUp();

        $this->repository = new ProjectRepository($this->model(Project::class));
    }

    /**
     * @test
     */
    public function can_be_instantiated(): void
    {
        $this->assertInstanceOf(ProjectRepository::class, $this->repository);
    }
}
