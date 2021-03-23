<?php

declare(strict_types=1);

namespace Tests\Unit\Events\Cluster;

use App\Events\Cluster\ClusterHasFailed;
use Tests\TestCase;

class ClusterHasFailedTest extends TestCase
{
    /**
     * @var ClusterHasFailed
     */
    private $event;

    /**
     * @var int
     */
    private $projectId = 998;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new ClusterHasFailed($this->projectId);
    }

    /**
     * @test
     */
    public function create_has_failed_has_public_cluster_id_property()
    {
        $this->assertEquals($this->projectId, $this->event->projectId);
    }
}
