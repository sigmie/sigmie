<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

trait API
{
    protected HttpConnection $httpConnection;

    public function setHttpConnection(HttpConnection $connection): void
    {
        $this->httpConnection = $connection;
    }

    public function getHttpConnection(): HttpConnection
    {
        return $this->httpConnection;
    }

    protected function httpCall(ElasticsearchRequest $request): ElasticsearchResponse
    {
        return ($this->httpConnection)($request);
    }
}
