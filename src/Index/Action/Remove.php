<?php

namespace Ni\Elastic\Index\Action;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;
use Ni\Elastic\Contract\Subscribable;

class Remove implements Action, Subscribable
{
    /**
     * Action data preparation
     *
     * @param string $data
     *
     * @return array
     */
    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }

    /**
     * Before event name
     *
     * @return string
     */
    public function beforeEvent(): string
    {
        return 'before.index.remove';
    }

    /**
     * Execute the elasticsearch call
     *
     * @param Elasticsearch $elasticsearch
     * @param array $params
     * @return array
     */
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->delete($params);
    }

    /**
     * After event name
     *
     * @return string
     */
    public function afterEvent(): string
    {
        return 'after.index.remove';
    }
}
