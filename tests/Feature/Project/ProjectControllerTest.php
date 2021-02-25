<?php

declare(strict_types=1);

namespace Tests\Feature\Project;

use App\Models\Project;
use Illuminate\Support\Facades\Config;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithSubscribedUser;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use WithSubscribedUser, WithProject, WithSubscribedUser;

    /**
     * @test
     */
    public function store_creates_project_and_redirects_to_cluster_create()
    {
        Config::set('override.provider.rule', true);

        $this->withSubscribedUser();

        $this->actingAs($this->user);

        $response = $this->post(route('project.store'), [
            'name' => 'foo',
            'description' => 'bar',
            'provider' => ['creds' => '{"foo":"bar"}', 'id' => 'cloud']
        ]);

        $project = $this->user->refresh()->projects->first();

        $this->assertEquals('foo', $project->name);
        $this->assertEquals('bar', $project->description);
        $this->assertEquals('cloud', $project->provider);
        $this->assertEquals(['foo' => 'bar'], $project->decryptedCloudCredentials());

        $response->assertRedirect(route('cluster.create'));
    }

    /**
     * @test
     */
    public function update_project_policy()
    {
        $this->withProject();
        $projectId = $this->project->id;

        $this->withProject();
        $this->actingAs($this->user);

        $response = $this->put(
            route('project.update', ['project' => $projectId]),
            ['name' => 'something', 'description' => 'something else']
        );

        $response->assertForbidden();
    }

    /**
     * @test
     */
    public function update_project_name_and_description()
    {
        $this->withProject();

        $this->actingAs($this->user);

        $this->put(
            route('project.update', ['project' => $this->project->id]),
            ['name' => 'something', 'description' => 'something else']
        );

        $this->project->refresh();

        $this->assertEquals('something', $this->project->name);
        $this->assertEquals('something else', $this->project->description);
    }

    /**
     * @test
     */
    public function create_renders_inertia_project_create()
    {
        $this->withSubscribedUser();

        $this->actingAs($this->user);

        $this->assertInertiaViewExists('project/create/create');
        $this->get(route('project.create'))->assertInertia('project/create/create');
    }
}
