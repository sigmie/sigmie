<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Create as CreateAction;
use Ni\Elastic\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;

class Create implements Action, Subscribable
{
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->create($params);
    }

    public function beforeEvent(): string
    {
        return 'before.index.create';
    }

    public function afterEvent(): string
    {
        return 'after.index.create';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data->getIdentifier()
        ];

        return $params;
    }
}
