<?php

namespace Sigma\Contract;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Element;

/**
 * Action Contract
 */
interface Action
{
    /**
     * Data prepare method
     *
     * @param Element|string $data
     * @return array
     */
    public function prepare($data): array;

    /**
     * Elasticsearch call
     *
     * @param Elasticsearch $elasticsearch
     * @param array $params
     *
     * @return array
     */
    public function execute(Elasticsearch $elasticsearch, array $params): array;
}
