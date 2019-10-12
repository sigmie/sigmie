<?php

namespace Ni\Elastic\Index\Actions;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;
use Ni\Elastic\Contract\Actions\Remove as RemoveAction;
use Ni\Elastic\Contract\Subscribable;

class Remove implements Action, Subscribable
{
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->delete($params);
    }

    public function beforeEvent(): string
    {
        return 'before.index.remove';
    }

    public function afterEvent(): string
    {
        return 'after.index.remove';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }
}
