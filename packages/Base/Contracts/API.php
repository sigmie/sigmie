<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JSONRequest;

trait API
{
    protected HttpConnection $httpConnection;

    public function setHttpConnection(HttpConnection $connection): self
    {
        $this->httpConnection = $connection;

        return $this;
    }

    public function getHttpConnection()
    {
        return $this->httpConnection;
    }

    protected function httpCall(JSONRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return ($this->httpConnection)($request, $responseClass);
    }
}
