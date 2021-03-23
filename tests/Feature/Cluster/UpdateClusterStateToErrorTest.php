<?php

declare(strict_types=1);

namespace Tests\Feature\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use App\Listeners\Cluster\UpdateClusterStateToError;
use App\Repositories\ClusterRepository;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class UpdateClusterStateToErrorTest extends TestCase
{
    use WithRunningCluster;
    /**
     * @var UpdateClusterStateToError
     */
    private $listener;

    public function setUp(): void
    {
        parent::setUp();

        $this->listener = new UpdateClusterStateToError();
    }

    /**
     * @test
     */
    public function handle_sets_cluster_state_to_error()
    {
        $this->withRunningCluster();

        $this->listener->handle(new ClusterHasFailed($this->project->id));

        $this->cluster->refresh();

        $this->assertEquals($this->cluster->state, 'failed');
    }
}
