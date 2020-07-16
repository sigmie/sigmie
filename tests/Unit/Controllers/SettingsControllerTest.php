<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\SettingsController;
use App\Models\Cluster;
use App\Models\Model;
use App\Models\Project;
use App\Repositories\ProjectRepository;
use Collator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use phpDocumentor\Reflection\Types\Integer;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;


class SettingsControllerTest extends TestCase
{
    /**
     * @var SettingsController
     */
    private $controller;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    /**
     * @var ProjectRepository|MockObject
     */
    private $projectRepositoryMock;

    /**
     * @var integer
     */
    private $projectId = 999;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var Collection|MockObject
     */
    private $clustersCollection;

    /**
     * @var Cluster|MockObject
     */
    private $clusterMock;

    /**
     * @var integer
     */
    private $clusterId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturn($this->clusterId);

        $this->clustersCollection = $this->createMock(Collection::class);

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturn($this->clustersCollection);

        $this->projectRepositoryMock = $this->createMock(ProjectRepository::class);
        $this->projectRepositoryMock->method('find')->willReturn($this->projectMock);

        $this->requestMock = $this->createMock(Request::class);
        $this->requestMock->expects($this->any())->method('get')->willReturn($this->projectId);

        $this->controller = new SettingsController($this->projectRepositoryMock);
    }

    /**
     * @test
     */
    public function index_method_renders_settings_index_with_cluster_id(): void
    {
        $this->clustersCollection->method('first')->willReturn($this->clusterMock);

        $this->controller->index($this->projectMock);

        Inertia::shouldReceive('settings/index', ['clusterId' => $this->clusterId]);
    }

    /**
     * @test
     */
    public function index_method_renders_settings_with_null_as_cluster_id_if_there_is_no_cluster(): void
    {
        $this->clustersCollection->method('first')->willReturn(null);

        $this->controller->index($this->projectMock);

        Inertia::shouldReceive('settings/index', ['clusterId' => null]);
    }
}
