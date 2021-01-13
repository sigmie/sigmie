<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JsonRequest;

interface Connection
{
    public function __invoke(JsonRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse;
}
