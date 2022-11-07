<?php

declare(strict_types=1);

namespace Sigmie\Base\APIs;

use Sigmie\Base\Contracts\ElasticsearchRequest;
use Sigmie\Base\Contracts\ElasticsearchResponse;
use Sigmie\Base\Contracts\ElasticsearchConnection;

trait API
{
    protected ElasticsearchConnection $elasticsearchConnection;

    public function setElasticsearchConnection(ElasticsearchConnection $connection): void
    {
        $this->elasticsearchConnection = $connection;
    }

    public function getElasticsearchConnection(): ElasticsearchConnection
    {
        return $this->elasticsearchConnection;
    }

    protected function elasticsearchCall(ElasticsearchRequest $request): ElasticsearchResponse
    {
        return ($this->elasticsearchConnection)($request);
    }
}
