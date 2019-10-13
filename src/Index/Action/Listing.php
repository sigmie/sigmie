<?php

namespace Ni\Elastic\Index\Action;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;
use Ni\Elastic\Contract\Subscribable;

class Listing implements Action, Subscribable
{
    /**
     * Before event name
     *
     * @return string
     */
    public function beforeEvent(): string
    {
        return 'before.index.listing';
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
        return $elasticsearch->cat()->indices($params);
    }

    /**
     * After event name
     *
     * @return string
     */
    public function afterEvent(): string
    {
        return 'after.index.listing';
    }

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
            'index' => $data,
        ];

        return $params;
    }
}
