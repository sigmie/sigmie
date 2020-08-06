<?php

declare(strict_types=1);


namespace Sigma\Document\Action;

use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Sigma\Event\Document\PostRemove;
use Sigma\Event\Document\PreRemove;
use Elasticsearch\Client as Elasticsearch;

class Remove implements Action, Subscribable
{
    public function prepare(...$data): array
    {
        [$index, $type, $identifier] = $data;

        $params = [
            'index' =>  $index,
            'id'    => $identifier,
            'type'    => $type
        ];

        return $params;
    }

    /**
     * Before event
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
        return $elasticsearch->delete($params);
    }

    /**
     * After event
     *
     * @return string
     */
    public function postEvent(): string
    {
        return PostRemove::class;
    }
}
