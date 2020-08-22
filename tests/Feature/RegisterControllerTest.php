<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Paddle\Subscription;
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
    public function registered_users_are_redirected_to_dashboard()
    {
        $user = factory(Subscription::class)->create()->billable;

        $this->actingAs($user);

        $response = $this->get(route('sign-up'));

        $response->assertRedirect(route('dashboard'));
    }
}
