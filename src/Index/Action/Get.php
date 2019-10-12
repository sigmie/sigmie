<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;

class Get implements Action, Subscribable
{
    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }

    public function beforeEvent(): string
    {
        return 'before.index.get';
    }

    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->get($params);
    }

    public function afterEvent(): string
    {
        return 'after.index.get';
    }
}
