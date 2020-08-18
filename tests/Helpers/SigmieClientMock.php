<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\Search\Cluster\Service as ClusterService;
use Sigmie\Search\Indices\Service as IndexService;
use Sigmie\Search\SigmieClient;

trait SigmieClientMock
{
    /**
     * @var SigmieClient|MockObject
     */
    private $sigmieClientMock;

    /**
     * @var ClusterService|MockObject
     */
    private $sigmieClusterServiceMock;

    /**
     * @var IndexService|MockObject
     */
    private $sigmieIndexServiceMock;

    public function createSigmieMock()
    {
        $this->sigmieClusterServiceMock = $this->createMock(ClusterService::class);
        $this->sigmieIndexServiceMock = $this->createMock(IndexService::class);

        $this->sigmieClientMock = $this->createMock(SigmieClient::class);

        $this->sigmieClientMock->method('indices')->willReturn($this->sigmieIndexServiceMock);
        $this->sigmieClientMock->method('cluster')->willReturn($this->sigmieClusterServiceMock);

        return $this->sigmieClientMock;
    }
}
