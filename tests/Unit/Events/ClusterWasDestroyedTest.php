<?php declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\ClusterWasDestroyed;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ClusterWasDestroyedTest extends TestCase
{
    /**
     * @var ClusterWasDestroyed
     */
    private $event;

    /**
     * @var integer
     */
    private $clusterId = 998;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new ClusterWasDestroyed($this->clusterId);
    }

    /**
     * @test
     */
    public function create_was_destroyed_has_public_cluster_id_property()
    {
        $this->assertEquals($this->clusterId, $this->event->clusterId);
    }

    /**
     * @test
     */
    public function cluster_was_booted_is_broadcasted_on_private_cluster_channel()
    {
        $this->assertEquals(new PrivateChannel("cluster.{$this->clusterId}"), $this->event->broadcastOn());
    }
}
