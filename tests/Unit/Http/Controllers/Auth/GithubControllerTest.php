<?php

namespace Tests\Unit\Http\Controllers\Auth;

use App\Http\Controllers\Auth\GithubController;
use Illuminate\Http\Request;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Tests\TestCase;

class GithubControllerTest extends TestCase
{
    /**
     * @var GithubController
     */
    private $controller;

    public function setUp(): void
    {
        parent::setUp();

        $this->controller = new GithubController();
    }

    /**
     * @test
     */
    public function foo(): void
    {
        $githubDriverMock = $this->createMock(SocialiteManager::class);
        $requestMock = $this->createMock(Request::class);

        $requestMock->expects($this->once())->method('get');


        Socialite::shouldReceive('driver')
            ->once()
            ->with('github')
            ->andReturn($githubDriverMock);

        $this->controller->redirect($requestMock);
    }
}
