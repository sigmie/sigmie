<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JsonRequest;

trait API
{
    protected Connection $connection;

    public function setConnection(Connection $connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    protected function call(JsonRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse
    {
        return ($this->connection)($request, $responseClass);
    }
}
