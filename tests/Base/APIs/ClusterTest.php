<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\APIs;

use Sigmie\Base\APIs\Calls\Cluster as ClusterAPI;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Testing\TestCase;
use Sigmie\Testing\TestConnection;
use Sigmie\Testing\TestIndex;

class ClusterTest extends TestCase
{
    use TestConnection, ClusterAPI, TestIndex;

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
