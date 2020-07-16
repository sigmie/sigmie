<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RedirectIfHasCluster;
use App\Http\Middleware\ShareProjectsToView;
use App\Http\Middleware\ShareProjectToView;
use App\Models\Project;
use App\Models\User;
use App\Repositories\ProjectRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Tests\Helpers\NeedsClosure;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Tests\TestCase;

class ShareProjectToViewTest extends TestCase
{
    use NeedsClosure;

    /**
     * @var RedirectIfHasCluster
     */
    private $middleware;

    /**
     * @var Request|MockObject
     */
    private $requestMock;

    /**
     * @var User|MockObject
     */
    private $userMock;

    /**
     * @var Project|MockObject
     */
    private $projectMock;
    /**
     * @var integer
     */
    private $projectId = 0;

    /**
     * @var Collection|MockObject
     */
    private $projectsCollectionMock;

    /**
     * @var array
     */
    private $projects = [['id' => 0, 'name' => 'bar'], ['id' => 9, 'name' => 'baz']];

    public function setUp(): void
    {
        parent::setUp();

        $this->closure();

        $this->projectMock = $this->createMock(Project::class);
        $this->projectMock->method('getAttribute')->willReturn($this->projectId);

        $this->requestMock = $this->createMock(Request::class);

        $this->projectsCollectionMock = $this->createMock(Collection::class);
        $this->projectsCollectionMock->method('first')->willReturn($this->projectMock);

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('getAttribute')->willReturn($this->projectsCollectionMock);

        Auth::shouldReceive('user')->once()->andReturn($this->userMock);

        $this->middleware = new ShareProjectToView;
    }

    /**
     * @test
     */
    public function handle_share_project_id_to_inertia(): void
    {
        $this->requestMock->method('get')->willReturn(99);

        Inertia::shouldReceive('share')->once()->with('project_id', 99);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }

    /**
     * @test
     */
    public function handle_shares_first_project_id_if_no_project_id_in_request()
    {
        $this->requestMock->method('get')->willReturn(null);

        Inertia::shouldReceive('share')->once()->with('project_id', $this->projectId);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }
}
