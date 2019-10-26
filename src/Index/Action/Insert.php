<?php

namespace Sigma\Index\Action;

use Sigma\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;
use Sigma\Index\Index;

class Insert implements Action, Subscribable
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
            'index' => $index->name
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
        return 'before.index.insert';
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
        return 'after.index.insert';
    }
}
