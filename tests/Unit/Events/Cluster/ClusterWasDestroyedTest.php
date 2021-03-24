<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Cluster;

use App\Events\Cluster\ClusterWasDestroyed;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\Helpers\WithDestroyedCluster;
use Tests\Helpers\WithRunningExternalCluster;
use Tests\TestCase;

class ClusterWasDestroyedTest extends TestCase
{
    use WithDestroyedCluster;

    /**
     * @var ClusterWasDestroyed
     */
    private $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withDestroyedCluster();

        $this->event = new ClusterWasDestroyed($this->project->id);
    }

    /**
     * @test
     */
    public function broadcast_as()
    {
        $this->assertEquals('cluster.destroyed', $this->event->broadcastAs());
    }

    /**
     * @test
     */
    public function create_was_destroyed_has_public_cluster_id_property()
    {
        $this->assertEquals($this->project->id, $this->event->projectId);
    }

    /**
     * @test
     */
    public function cluster_was_booted_is_broadcasted_on_private_cluster_channel()
    {
        $this->assertEquals(new PrivateChannel("{$this->cluster->getMorphClass()}.{$this->cluster->id}"), $this->event->broadcastOn());
    }
}
