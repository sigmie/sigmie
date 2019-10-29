<?php

namespace Sigma\Document\Action;

use Sigma\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Index\Index;
use Sigma\Contract\Action;
use Sigma\Document\Document;

class Insert implements Action
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
     * Before event name
     *
     * @return string
     */
    public function beforeEvent(): string
    {
        return 'before.document.insert';
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
     * After event name
     *
     * @return string
     */
    public function afterEvent(): string
    {
        return 'after.document.insert';
    }
}