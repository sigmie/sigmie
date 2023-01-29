<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Http\Promise\Promise;

interface ElasticsearchConnection
{
    public function __invoke(ElasticsearchRequest $request): ElasticsearchResponse;

    public function promise(ElasticsearchRequest $request): Promise;
}
