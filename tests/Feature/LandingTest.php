<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LandingTest extends TestCase
{
    /**
     * @test
     */
    public function authenticated_user_is_redirected_to_dashboard()
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->get('/')->assertRedirect('/dashboard');
    }

    /**
     * @test
     */
    public function landing_page_response_is_200()
    {
        $this->get(route('landing'))->assertOk();
    }
}
