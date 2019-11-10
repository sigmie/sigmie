<?php

declare(strict_types=1);

namespace Sigma\Document\Action;

use Sigma\Contract\Action;
use Sigma\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Sigma\Event\Document\PostMerge;
use Sigma\Event\Document\PreMerge;

class Merge implements Action, Subscribable
{
    public function prepare(...$data): array
    {
        [$index, $type, $id, $body] = $data;

        $params = [
            'index' => $index,
            'type' => $type,
            'id'    => $id,
            'body'  => [
                'doc' => $body
            ]
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
        return PreMerge::class;
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
        return $elasticsearch->update($params);
    }

    /**
     * After event
     *
     * @return string
     */
    public function postEvent(): string
    {
        return PostMerge::class;
    }
}
