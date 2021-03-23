<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Listeners\Cluster\UpdateClusterStateToError;
use App\Repositories\ClusterRepository;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class RedirectIfHasClusterTest extends TestCase
{
    use WithRunningCluster;

    /**
     * @test
     */
    public function redirect_to_dashboard_if_has_cluster()
    {
        $this->withRunningCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.create', ['project_id' => $this->project->id]));

        $res->assertForbidden();
    }
}
