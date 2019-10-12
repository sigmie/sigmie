<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Get as GetAction;
use Ni\Elastic\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\IndexCollection;

class Get implements Action, Subscribable
{
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->get($params);
    }

    public function beforeEvent(): string
    {
        return 'before.index.get';
    }

    public function afterEvent(): string
    {
        return 'after.index.get';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }
}
