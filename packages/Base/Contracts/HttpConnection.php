<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Sigmie\Base\Http\ElasticsearchResponse;

interface HttpConnection
{
    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse;
}
