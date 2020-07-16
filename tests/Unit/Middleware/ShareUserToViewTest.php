<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RedirectIfHasCluster;
use App\Http\Middleware\ShareProjectsToView;
use App\Http\Middleware\ShareUserToView;
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

class ShareUserToViewTest extends TestCase
{
    use NeedsClosure;

    /**
     * @var ShareUserToView
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

    public function setUp(): void
    {
        parent::setUp();

        $this->closure();

        $this->userMock = $this->createMock(User::class);

        Auth::shouldReceive('user')->andReturn($this->userMock);

        $this->middleware = new ShareUserToView;
    }

    /**
     * @test
     */
    public function handle_shares_user_data_if_user_is_authenticated()
    {

        $this->userMock->method('only')->willReturn(['username', 'avatar-url']);

        $this->userMock->expects($this->once())->method('only')->with(['id', 'avatar_url']);

        Auth::shouldReceive('check')->once()->andReturn(true);
        Inertia::shouldReceive('share')->once()->with('user', ['username', 'avatar-url']);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }

    /**
     * @test
     */
    public function handle_shares_null_if_user_is_not_authenticated()
    {
        Auth::shouldReceive('check')->once()->andReturn(false);

        Inertia::shouldReceive('share')->once()->with('user', null);

        $this->expectClosureCalledWith($this->requestMock);

        $this->middleware->handle($this->requestMock, $this->closureMock);
    }
}
