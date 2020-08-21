<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
    public function registered_users_are_redirected_to_dashboard()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $response = $this->get(route('register'));

        $response->assertRedirect(route('dashboard'));
    }
}
