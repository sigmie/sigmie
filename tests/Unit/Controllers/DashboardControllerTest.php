<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Http\Controllers\DashboardController;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Laravel\Nova\Console\DashboardCommand;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
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

    public function setUp(): void
    {
        parent::setUp();

        $this->requestMock = $this->createMock(Request::class);

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturnMap([
            ['state', 'some-state'],
            ['id', 'some-id']
        ]);

        $this->controller = new DashboardController;
    }

    /**
     * @test
     */
    public function render_inertia_dashboard_with_state_and_id()
    {
        Gate::shouldReceive('authorize')->once()->with('view-dashboard', $this->projectMock);

        Inertia::shouldReceive('render')->once()->with('dashboard', ['state' => 'some-state', 'id' => 'some-id']);

        ($this->controller)($this->requestMock, $this->projectMock);
    }
}
