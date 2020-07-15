<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function gest_redirects_to_login()
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    /**
     * @test
     */
    public function assign_first_project_id_if_no_project_id_is_provided()
    {
        $project = factory(Project::class)->create();

        $this->actingAs($project->getAttribute('user'));

        $response = $this->get('/dashboard');

        $response->assertRedirect("/dashboard/{$project->id}");
    }

    /**
     * @test
     */
    public function redirect_to_create_project_if_no_project_exists()
    {
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('project.create'));
    }
}
