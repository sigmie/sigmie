<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Subscription;
use Tests\TestCase;

class ProjectPoliciesTest extends TestCase
{
    /**
     * @test
     */
    public function project_create_is_allowed_only_if_user_doesn_have_project()
    {

        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->get(route('project.create'));

        $response->assertForbidden();
    }
}
