<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    /**
     * @test
     */
    public function project_user_returns_belongs_to()
    {
        $project = Project::factory()->make();

        $this->assertInstanceOf(BelongsTo::class, $project->user());
    }

    /**
     * @test
     */
    public function project_clusters_returns_has_many()
    {
        $project = Project::factory()->make();

        $this->assertInstanceOf(HasMany::class, $project->clusters());
    }

    /**
     * @test
     */
    public function project_store_action()
    {
        Config::set('override.provider.rule', true);

        $user = Subscription::factory()->create()->billable;

        $this->actingAs($user);

        $response = $this->post(route('project.store'), [
            'name' => 'foo',
            'description' => 'bar',
            'provider' => ['id' => 'google', 'creds' => '[]'],
            'user_id' => $user->getAttribute('id'),
        ]);

        $response->assertRedirect(route('cluster.create'));

        $this->assertDatabaseHas('projects', [
            'name' => 'foo',
            'description' => 'bar',
            'provider' => 'google',
            'user_id' => $user->getAttribute('id')
        ]);
    }
}
