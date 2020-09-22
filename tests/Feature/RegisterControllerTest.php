<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Laravel\Paddle\Subscription;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function sign_in_route()
    {
        $response = $this->get(route('sign-in'));

        $response->assertOk();
    }

    /**
     * @test
     */
    public function create_user_avatar_url()
    {
        $email = 'foo@bar.com';
        $expectedUrl = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($email))) . '?d=identicon';

        $this->post(route('register'), [
            'email' => $email,
            'username' => 'johnys_place',
            'password' => 'demo320239',
            'avatar_url' => 'https://some.other-url.com',
            'github' => false
        ]);

        $this->assertDatabaseHas('users', [
            'avatar_url' => $expectedUrl,
        ]);
    }

    /**
     * @test
     */
    public function create_github_user()
    {
        Event::fake();

        $response = $this->post(route('register'), [
            'email' => 'john@gmai.com',
            'username' => 'johnys_place',
            'password' => 'demo',
            'avatar_url' => 'john@gmai.com',
            'github' => true
        ]);

        Event::assertDispatched(Registered::class);

        $response->assertJson(['registered' => true]);

        $this->assertTrue(Auth::check());

        $this->assertDatabaseHas('users', [
            'email' => 'john@gmai.com',
            'username' => 'johnys_place',
            'avatar_url' => 'john@gmai.com',
            'github' => true
        ]);
    }

    /**
     * @test
     */
    public function show_register_form_renders_without_githubuser()
    {
        $this->expectsInertiaToRender(
            'auth/register',
            [
                'githubUser' => null,
            ]
        );

        $response = $this->get(route('sign-up'));
        $response->assertOk();
    }

    /**
     * @test
     */
    public function show_register_form_with_github_user_data()
    {
        $this->expectsInertiaToRender(
            'auth/register',
            [
                'githubUser' => 'some user data',
            ]
        );

        $this->withSession(['githubUser' => 'some user data']);

        $response = $this->get(route('sign-up'));
        $response->assertOk();
    }

    /**
     * @test
     */
    public function registered_users_are_redirected_to_dashboard()
    {
        $user = factory(Subscription::class)->create()->billable;

        $this->actingAs($user);

        $response = $this->get(route('sign-up'));

        $response->assertRedirect(route('dashboard'));
    }
}
