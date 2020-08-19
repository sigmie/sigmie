<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function paylink_creates_user_and_returns_link()
    {
        $paylink = 'http://foo.bar';

        Http::shouldReceive('post')->andReturn(['response' => ['url' => $paylink], 'success' => true]);

        $response = $this->post(route('paylink'), [
            'email' => 'foo@bar.com',
            'password' => 'baz12345',
            'username' => 'John Doe'
        ]);

        $response->assertJson(['paylink' => $paylink]);

        $this->assertDatabaseHas('users', [
            'email' => 'foo@bar.com',
            'username' => 'John Doe'
        ]);
    }

    /**
     * @test
     */
    public function render_register_with_paddle_vendor()
    {
        Config::set('services.paddle.vendor_id', '89043202');

        Inertia::shouldReceive('render')->with('auth/register', [
            'githubUser' => [],
            'paddleData' => [
                'vendor' => 89043202
            ]
        ]);

        $this->get(route('register'));
    }

    /**
     * @test
     */
    public function registered_users_are_redirected_to_dashboard()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $response = $this->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    }
}
