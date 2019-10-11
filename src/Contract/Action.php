<?php

namespace Ni\Elastic\Contract;

use Elasticsearch\Client as Elasticsearch;

interface Action
{
    public function prepare($data): array;

    public function execute(Elasticsearch $elasticsearch, array $params): array;
}
