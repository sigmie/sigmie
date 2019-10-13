<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;

class Get implements Action, Subscribable
{
    /**
     * Action data preparation
     *
     * @param string $identifier
     *
     * @return array
     */
    public function prepare($identifier): array
    {
        $params = [
            'index' => $identifier
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
        return 'before.index.get';
    }

    /**
     * Execute the elasticsearch call
     *
     * @param Elasticsearch $elasticsearch
     * @param array $params
     *
     * @return array
     */
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->indices()->get($params);
    }

    /**
     * After event name
     *
     * @return string
     */
    public function afterEvent(): string
    {
        return 'after.index.get';
    }
}
