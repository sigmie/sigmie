<?php

namespace Sigma\Index\Action;

use Sigma\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;

class Get implements Action
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
}
