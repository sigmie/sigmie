<?php

namespace Sigma\Index\Action;

use Sigma\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;
use Sigma\Index\Index;

class Create implements Action, Subscribable
{
    /**
     * Action data preparation
     *
     * @param Index $data
     *
     * @return array
     */
    public function prepare($index): array
    {
        $params = [
            'index' => $index->getIdentifier()
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
        return 'before.index.create';
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
        return $elasticsearch->indices()->create($params);
    }

    /**
     * After event name
     *
     * @return string
     */
    public function afterEvent(): string
    {
        return 'after.index.create';
    }
}
