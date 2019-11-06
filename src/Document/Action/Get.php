<?php

namespace Sigma\Document\Action;

use Sigma\Contract\Action;
use Elasticsearch\Client as Elasticsearch;

class Get implements Action
{
    public function prepare(...$params): array
    {
        [$index, $id, $type] = $params;

        $params = [
            'index' => $index,
            'id'    => $id,
            'type' => $type
        ];

        return $params;
    }

    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->get($params);
    }
}
