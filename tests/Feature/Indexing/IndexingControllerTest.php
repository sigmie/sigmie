<?php

declare(strict_types=1);

namespace Tests\Feature\Indexing;

use App\Http\Controllers\Indexing\PlanController;
use App\Models\IndexingPlan;
use Tests\Helpers\WithIndexingPlan;
use Tests\Helpers\WithNotSubscribedUser;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class IndexingControllerTest extends TestCase
{
    use WithRunningCluster, WithNotSubscribedUser, WithIndexingPlan;

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
    public function indexing_lists_plans_only_from_active_project_and_without_cluster_infos()
    {
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();
        IndexingPlan::factory()->create();

        $this->withIndexingPlan();

        $this->actingAs($this->user);

        $res = $this->get(route('indexing.indexing', ['project' => $this->cluster->project->id]));

        $plans = IndexingPlan::where('cluster_id', '=', $this->cluster->id)->get(
            [
                'indexing_plans.id',
                'indexing_plans.name',
                'indexing_plans.description',
                'indexing_plans.state',
                'indexing_plans.type',
                'indexing_plans.webhook_url',
                'indexing_plans.deactivated_at',
                'indexing_plans.created_at',
                'indexing_plans.updated_at',
                'indexing_plans.run_at'
            ]
        );

        $this->assertNotEquals(count($plans), count(IndexingPlan::all()));

        $res->assertInertia('indexing/indexing')->assertInertiaHas('plans', $plans);
    }
}
