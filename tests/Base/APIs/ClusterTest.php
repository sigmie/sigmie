<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Cluster as ClusterAPI;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Testing\TestCase;

class ClusterTest extends TestCase
{
    use ClusterAPI;

    /**
     * @test
     */
    public function cluster_api_call(): void
    {
        $res = $this->clusterAPICall('/health');

        $this->assertInstanceOf(ElasticsearchResponse::class, $res);
        $this->assertArrayHasKey('status', $res->json());
    }
}
