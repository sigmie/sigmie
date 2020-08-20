<?php

namespace Tests\Feature;

use Laravel\Socialite\Contracts\User as SocialiteUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Tests\TestCase;

class GithubControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function redirect_depends_on_action_param()
    {
        $url = 'redirect_uri=' . urlencode(route('github.register'));

        $response = $this->get(route('github.redirect', ['action' => 'register']));

        $targetUrl = $response->baseResponse->getTargetUrl();

        $this->assertStringContainsString($url, $targetUrl);

        $url = 'redirect_uri=' . urlencode(route('github.login'));

        $response = $this->get(route('github.redirect', ['action' => 'login']));

        $targetUrl = $response->baseResponse->getTargetUrl();

        $this->assertStringContainsString($url, $targetUrl);
    }

    /**
     * @test
     */
    public function register_populates_session_with_github_user_info()
    {
        $githubUser = $this->createMock(SocialiteUser::class);
        $githubUser->method('getName')->willReturn('John');
        $githubUser->method('getEmail')->willReturn('foo@bar.com');
        $githubUser->method('getAvatar')->willReturn('https://awesome.avatar-url.com');

        $githubDriver = $this->createMock(GithubProvider::class);
        $githubDriver->method('user')->willReturn($githubUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($githubDriver);

        $response = $this->get(route('github.register'));

        $response->assertSessionHas('githubUser');
        $response->assertSessionHasAll(['githubUser' => [
            'name' => 'John',
            'email' => 'foo@bar.com',
            'avatar_url' => 'https://awesome.avatar-url.com',
        ]]);
    }
}
