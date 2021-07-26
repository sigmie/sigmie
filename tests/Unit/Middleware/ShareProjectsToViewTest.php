<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\Shares\ShareProjectsToView;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Tests\Helpers\WithClosureMock;
use Tests\TestCase;

class ShareProjectsToViewTest extends TestCase
{
    use WithClosureMock;

    /**
     * @var ShareProjectsToView
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

        $this->withClosureMock();

        $this->projectMock = $this->createMock(Project::class);

        $this->projectsCollectionMock = $this->createMock(Collection::class);
        $this->projectsCollectionMock->method('map')->willReturn($this->projects);

        $this->userMock = $this->createMock(User::class);
        $this->userMock->method('getAttribute')->willReturn($this->projectsCollectionMock);

        $this->middleware = new ShareProjectsToView();
    }

    /**
     * @test
     */
    public function handle_redirects_to_login_if_not_authenticated(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(false);

        $response = $this->middleware->handle($this->requestMock, $this->closureMock);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals($response->getTargetUrl(), route('login'));
    }

    /**
     * @test
     */
    public function handle_share_projects_to_inertia(): void
    {
        Auth::shouldReceive('check')->once()->andReturn(true);
        Auth::shouldReceive('user')->once()->andReturn($this->userMock);

        $this->expectClosureMockCalledWith($this->requestMock);
        Inertia::shouldReceive('share')->once()->with('projects', $this->projects);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }
}
