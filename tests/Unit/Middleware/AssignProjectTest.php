<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\AssignProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Mockery\Mock;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\Helpers\NeedsClosure;
use Tests\TestCase;

class AssignProjectTest extends TestCase
{
    use NeedsClosure;

    /**
     * @var AssignProject
     */
    private $middleware;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    /**
     * @var Collection|MockObject
     */
    private $projectsCollectionMock;

    /**
     * @var User|MockObject
     */
    private $userMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;

    /**
     * @var int
     */
    private $projectId = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->closure();

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturn($this->projectId);

        $this->projectsCollectionMock = $this->createMock(Collection::class);

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('getAttribute')->willReturnMap([['projects', $this->projectsCollectionMock]]);

        $this->requestMock = $this->getMockBuilder(Request::class)->addMethods(['getName'])->setMethods(['route'])->getMock();

        $this->middleware = new AssignProject;
    }

    /**
     * @test
     */
    public function dont_redirect_if_projectd(): void
    {
        $this->projectsCollectionMock->method('first')->willReturn($this->projectMock);
        $this->requestMock->expects($this->once())->method('route')->willReturn(new Project);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }

    /**
     * @test
     */
    public function redirect_to_first_project_if_no_project_passed()
    {
        $this->projectsCollectionMock->method('first')->willReturn($this->projectMock);
        $this->requestMock->expects($this->any())->method('route')->willReturn(null, $this->requestMock);
        $this->requestMock->expects($this->any())->method('getName')->willReturn('dashboard');

        Auth::shouldReceive('user')->andReturn($this->userMock);

        $response = $this->middleware->handle($this->requestMock, $this->closureMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('dashboard', ['project' => $this->projectId]), $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function redirect_to_create_if_not_project_is_found()
    {
        Auth::shouldReceive('user')->andReturn($this->userMock);
        $this->projectsCollectionMock->method('first')->willReturn(null);

        $response = $this->middleware->handle($this->requestMock, $this->closureMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals(route('project.create'), $response->getTargetUrl());
    }
}
