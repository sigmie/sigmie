<?php

declare(strict_types=1);

namespace Sigmie\Tests\Integration;

use Sigmie\Search\Cluster\Cluster;
use Sigmie\Search\Cluster\Service as ClusterService;
use Sigmie\Tests\Helpers\IntegrationTestCase;

class ClusterServiceTest extends IntegrationTestCase
{
    /**
     * @var ClusterService
     */
    private $service;

    public function setUp(): void
    {
        $this->service = new ClusterService($this->client());
    }

    /**
     * @test
     */
    public function cluster_test()
    {
        $cluster = $this->service->get();

        $this->assertInstanceOf(Cluster::class, $cluster);
    }
}
