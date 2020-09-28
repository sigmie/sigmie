<?php

declare(strict_types=1);

namespace Tests\Unit\Events;

use App\Events\Cluster\ClusterWasCreated;
use Tests\TestCase;

class ClusterWasCreatedTest extends TestCase
{
    /**
     * @var ClusterWasCreated
     */
    private $event;

    /**
     * @var integer
     */
    private $clusterId = 999;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new ClusterWasCreated($this->clusterId);
    }

    /**
     * @test
     */
    public function create_was_created_has_public_cluster_id_property()
    {
        $this->assertEquals($this->clusterId, $this->event->clusterId);
    }
}
