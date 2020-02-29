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
}
