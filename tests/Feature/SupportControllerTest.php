<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Inertia\Inertia;
use Tests\TestCase;

class SupportControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function see_support_page_controller_and_inertia_view()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $this->get(route('support'))->assertOk();

        Inertia::shouldReceive('render')->with('support/index');
    }
}
