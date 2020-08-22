<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Inertia;
use Laravel\Paddle\Subscription;
use Tests\TestCase;

class SupportControllerTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function see_support_page_controller_and_inertia_view()
    {
        $user = factory(Subscription::class)->create()->billable;

        $this->actingAs($user);

        $this->get(route('support'))->assertOk();

        Inertia::shouldReceive('render')->with('support/index');
    }
}
