<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

interface ElasticsearchConnection
{
    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse;
}
