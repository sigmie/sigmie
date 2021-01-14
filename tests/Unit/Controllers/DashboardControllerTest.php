<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\Dashboard\DashboardController;
use App\Models\Cluster;
use App\Models\Project;
use App\Repositories\ClusterRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\Search\SigmieClient;
use Tests\Helpers\SigmieClientMock;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use SigmieClientMock;

    /**
     * @var DashboardController
     */
    private $controller;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var ClusterRepository|MockObject
     */
    private $clusterRepositoryMock;

    /**
     * @var MockObject|Cluster
     */
    private $clusterMock;

    /**
     * @var SigmieClient|MockObject
     */
    private $sigmieClientMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->createSigmieMock();

        $this->requestMock = $this->createMock(Request::class);

        $this->projectMock = $this->createMock(Project::class);

        $this->clusterMock = $this->createMock(Cluster::class);
        $this->clusterMock->method('getAttribute')->willReturnMap([
            ['state', 'some-state'],
            ['id', 'some-id']
        ]);

        $this->clusterRepositoryMock = $this->createMock(ClusterRepository::class);
        $this->clusterRepositoryMock->method('findOneTrashedBy')->willReturn($this->clusterMock);

        $this->controller = new DashboardController($this->clusterRepositoryMock);
    }

    /**
     * @test
     */
    public function render_inertia_dashboard_with_state_and_id()
    {
        Gate::shouldReceive('authorize')->once()->with('view-dashboard', $this->projectMock);

        $this->assertInertiaViewExists('dashboard/dashboard', ['clusterId' => 'some-id']);

        $this->controller->show($this->projectMock);
    }
}
