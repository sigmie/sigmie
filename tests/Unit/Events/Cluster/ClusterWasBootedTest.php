<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Cluster;

use App\Events\Cluster\ClusterWasBooted;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class ClusterWasBootedTest extends TestCase
{
    /**
     * @var ClusterWasBooted
     */
    private $event;

    /**
     * @var int
     */
    private $clusterId = 998;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new ClusterWasBooted($this->clusterId);
    }

    /**
     * @test
     */
    public function create_was_booted_has_public_cluster_id_property()
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
