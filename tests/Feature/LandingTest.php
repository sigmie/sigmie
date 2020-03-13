<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LandingTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function authenticated_user_is_redirected_to_dashboard()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $this->get('/')->assertRedirect('/dashboard');
    }
}
