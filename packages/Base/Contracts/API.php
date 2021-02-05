<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JSONRequest;

trait API
{
    public static HttpConnection $httpConnection;

    public function setHttpConnection(HttpConnection $connection): void
    {
        self::$httpConnection = $connection;
    }

    public function getHttpConnection()
    {
        return self::$httpConnection;
    }

    protected function httpCall(JSONRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return (self::$httpConnection)($request, $responseClass);
    }
}
