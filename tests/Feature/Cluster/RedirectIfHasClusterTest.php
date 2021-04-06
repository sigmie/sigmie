<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class RedirectIfHasClusterTest extends TestCase
{
    use WithRunningExternalCluster;

    /**
     * @test
     */
    public function redirect_to_dashboard_if_has_cluster()
    {
        $this->withRunningExternalCluster();

        $this->actingAs($this->user);

        $res = $this->get(route('cluster.create', ['project_id' => $this->project->id]));

        $res->assertForbidden();
    }
}
