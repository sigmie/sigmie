<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Paddle\Subscription;
use Tests\TestCase;

class ProjectPoliciesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function project_create_is_allowed_only_if_user_doesn_have_project()
    {
        $user = factory(Subscription::class)->create()->billable;

        factory(Project::class)->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->get(route('project.create'));

        $response->assertForbidden();
    }
}
