<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;

trait API
{
    private static HttpConnection $httpConnection;

    public function setHttpConnection(HttpConnection $connection): void
    {
        self::$httpConnection = $connection;
    }

    public function getHttpConnection()
    {
        return self::$httpConnection;
    }

    protected function httpCall(ElasticsearchRequest $request): ElasticsearchResponse
    {
        return (self::$httpConnection)($request);
    }
}
