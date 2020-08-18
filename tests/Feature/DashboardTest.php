<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cluster;
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
    public function user_can_see_dashboard_only_from_owned_project()
    {
        $cluster = factory(Cluster::class)->create();
        $project = $cluster->getAttribute('project');

        $user = factory(User::class)->create();

        $this->actingAs($user);

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
        $project = factory(Project::class)->create();
        $user = factory(User::class)->create();

        $this->actingAs($user);

        $response = $this->get(route('dashboard', ['project' => $project->getAttribute('id')]));
        $response->assertRedirect(route('cluster.create'));
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
