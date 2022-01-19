<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface HttpConnection
{
    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse;
}
