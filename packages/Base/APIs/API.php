<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\ElasticsearchConnection;

trait API
{
    protected ElasticsearchConnection $httpConnection;

    public function setElasticsearchConnection(ElasticsearchConnection $connection): void
    {
        $this->httpConnection = $connection;
    }

    public function getElasticsearchConnection(): ElasticsearchConnection
    {
        return $this->httpConnection;
    }

    protected function elasticsearchCall(ElasticsearchRequest $request): ElasticsearchResponse
    {
        return ($this->httpConnection)($request);
    }
}
