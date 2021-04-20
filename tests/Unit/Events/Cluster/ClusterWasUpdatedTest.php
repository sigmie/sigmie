<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Cluster;

use App\Events\Cluster\ClusterWasUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\Helpers\WithRunningInternalCluster;
use Tests\TestCase;

class ClusterWasUpdatedTest extends TestCase
{
    use WithRunningInternalCluster;

    /**
     * @test
     */
    public function cluster_was_updated_arguments()
    {
        $this->withRunningInternalCluster();

        $event = new ClusterWasUpdated($this->project->id);
        $this->assertEquals(new PrivateChannel("cluster.{$this->cluster->id}"), $event->broadcastOn());
        $this->assertEquals('cluster.updated', $event->broadcastAs());
    }
}
