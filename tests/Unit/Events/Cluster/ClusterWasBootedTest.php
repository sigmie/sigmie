<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Cluster;

use App\Events\Cluster\ClusterWasBooted;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\Helpers\WithRunningCluster;
use Tests\TestCase;

class ClusterWasBootedTest extends TestCase
{
    use WithRunningCluster;
    /**
     * @var ClusterWasBooted
     */
    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withRunningCluster();

        $this->event = new ClusterWasBooted($this->project->id);
    }

    /**
     * @test
     */
    public function create_was_booted_has_public_cluster_id_property()
    {
        $this->assertEquals($this->project->id, $this->event->projectId);
    }

    /**
     * @test
     */
    public function broadcast_as()
    {
        $this->assertEquals('cluster.booted', $this->event->broadcastAs());
    }

    /**
     * @test
     */
    public function cluster_was_booted_is_broadcasted_on_private_cluster_channel()
    {
        $this->assertEquals(new PrivateChannel("{$this->cluster->getMorphClass()}.{$this->cluster->id}"), $this->event->broadcastOn());
    }
}
