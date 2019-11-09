<?php

declare(strict_types=1);


namespace Sigma\Index\Action;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;

class Listing implements Action
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
            'index' => $index,
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
        return $elasticsearch->cat()->indices($params);
    }
}
