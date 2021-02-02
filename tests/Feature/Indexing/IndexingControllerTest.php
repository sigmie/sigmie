<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Http\Controllers\Indexing\PlanController;
use App\Models\IndexingPlan;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithProject;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class IndexingControllerTest extends TestCase
{
    use WithRunningCluster, WithNotSubscribedUser, WithIndexingPlan, WithProject;

    /**
     * @test
     */
    public function redirects_to_same_route_with_project()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $this->get(route('indexing.indexing'))
            ->assertRedirect(route('indexing.indexing', ['project' => $this->project->id]));
    }

    /**
     * @test
     */
    public function can_view_only_owning_project()
    {
        $this->withRunningCluster();

        $projectId = $this->project->id;

        $this->withProject();
        $this->actingAs($this->user);

        $res = $this->get(route('indexing.indexing', ['project' => $projectId]));

        $res->assertStatus(403);
    }

    /**
     * @test
     */
    public function indexing_lists_plans_only_from_active_project_and_without_cluster_infos()
    {
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();

        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $res = $this->get(route('indexing.indexing', ['project' => $this->cluster->project->id]));

        $plans = IndexingPlan::where('project_id', $this->project->id)
            ->with('type')
            ->get()
            ->map(fn ($plan) => $plan->only([
                'id',
                'name',
                'description',
                'state',
                'type_type',
                'ping_url',
                'deactivated_at',
                'created_at',
                'type',
                'updated_at',
                'run_at',
            ]));

        $this->assertNotEquals(count($plans), count(IndexingPlan::all()));

        $res->assertInertia('indexing/indexing')->assertInertiaHas('plans', $plans);
    }
}
