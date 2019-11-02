<?php

namespace Sigma\Document\Action;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Index\Index;
use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Sigma\Event\Document\PostInsert;
use Sigma\Event\Document\PreInsert;

class Insert implements Action, Subscribable
{
    /**
     * Action data preparation
     *
     * @param Index $data
     *
     * @return array
     */
    public function prepare($document): array
    {
        return $document->toArray();
    }

    /**
     * Before event
     *
     * @return string
     */
    public function preEvent(): string
    {
        return PreInsert::class;
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
        return $elasticsearch->index($params);
    }

    /**
     * After event
     *
     * @return string
     */
    public function postEvent(): string
    {
        return PostInsert::class;
    }
}
