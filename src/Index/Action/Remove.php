<?php

namespace Sigma\Index\Action;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Sigma\Event\Index\PostRemove;
use Sigma\Event\Index\PreRemove;

class Remove implements Action, Subscribable
{
    /**
     * Action data preparation
     *
     * @param string $data
     *
     * @return array
     */
    public function prepare(...$data): array
    {
        [$index] = $data;

        $params = [
            'index' => $index
        ];

        return $params;
    }

    /**
     * Before event name
     *
     * @return string
     */
    public function preEvent(): string
    {
        return PreRemove::class;
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
        return $elasticsearch->indices()->delete($params);
    }

    /**
     * After event name
     *
     * @return string
     */
    public function postEvent(): string
    {
        return PostRemove::class;
    }
}
