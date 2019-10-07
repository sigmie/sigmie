<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Collection;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Action\IndexCreate;
use Ni\Elastic\Miscellaneous\Handler;
use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Response\ResponseHandler;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\Action\IndexDelete;
use Ni\Elastic\Index\Action\IndexGet;
use Ni\Elastic\Index\Action\IndexListing as IndexListing;

class Manager
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    private $handler;

    public function __construct(Elasticsearch $elasticsearch, ResponseHandler $handler)
    {
        $this->elasticsearch = $elasticsearch;
        $this->handler = $handler;
    }

    public function create($index)
    {
        $params = [
            'index' => $index->getIdentifier()
        ];

        $response = $this->elasticsearch->indices()->create($params);

        return $this->handler->handle($response, new IndexCreate);
    }

    public function remove(string $identifier): bool
    {
        $params = [
            'index' => $identifier
        ];

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->handler->handle($response, new IndexDelete);
    }

    public function list(string $name = '*'): IndexCollection
    {
        $params = [
            'index' => $name,
        ];

        $response = $this->elasticsearch->cat()->indices($params);

        return $this->handler->handle($response, new IndexListing);
    }

    public function get(string $name): Collection
    {
        $params = [
            'index' => $name
        ];

        $response = $this->elasticsearch->indices()->get($params);

        return $this->handler->handle($response, new IndexGet);
    }
}
