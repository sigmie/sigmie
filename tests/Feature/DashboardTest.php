<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Project;
use App\Models\Subscription;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    /**
     * @test
     */
    public function gest_redirects_to_login()
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('sign-in'));
    }

    /**
     * @test
     */
    public function assign_first_project_id_if_no_project_id_is_provided()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->actingAs($project->getAttribute('user'));

        $response = $this->get('/dashboard');

        $response->assertRedirect("/dashboard/{$project->id}");
    }

    /**
     * @test
     */
    public function user_can_see_dashboard_only_from_owned_project()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);
        $cluster = Cluster::factory()->create(['project_id' => $project->id]);

        $secondUser = Subscription::factory()->create()->billable;

        $this->actingAs($secondUser);

        $response = $this->get(route('dashboard', ['project' => $project->getAttribute('id')]));
        $response->assertForbidden();

        $this->actingAs($project->getAttribute('user'));

        $response = $this->get(route('dashboard', ['project' => $project->getAttribute('id')]));
        $response->assertSuccessful();
    }

    /**
     * @test
     */
    public function dashboard_redirects_if_project_has_not_a_cluster()
    {
        $user = Subscription::factory()->create()->billable;
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->get(route('dashboard', ['project' => $project->getAttribute('id')]));
        $response->assertRedirect(route('cluster.create'));
    }

    /**
     * @test
     */
    public function redirect_to_create_project_if_no_project_exists()
    {
        $user = Subscription::factory()->create()->billable;

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('project.create'));
    }
}
