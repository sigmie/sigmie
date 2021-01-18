<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;
use Sigmie\Http\Contracts\JSONRequest;

interface HttpConnection
{
    public function __invoke(JSONRequest $request, string $responseClass = ElasticsearchResponse::class): ElasticsearchResponse;
}
