<?php

namespace Ni\Elastic\Index;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Handler;
use Ni\Elastic\Element;
use Ni\Elastic\Contract\Manager;
use Ni\Elastic\Index\Action\CreateResponse;
use Ni\Elastic\Index\Action\IndexCreate;
use Ni\Elastic\Response\Factory;
use Ni\Elastic\Response\Response;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\Action\DeleteResponse;
use Ni\Elastic\Index\Action\GetResponse;
use Ni\Elastic\Index\Action\ListResponse as ListResponse;

class IndexManager implements Manager
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    private $handler;

    public function __construct(Elasticsearch $elasticsearch, Handler $handler)
    {
        $this->elasticsearch = $elasticsearch;
        $this->handler = $handler;
    }

    public function create(Element $index): Element
    {
        $params = [
            'index' => $index->getIdentifier()
        ];

        $response = $this->elasticsearch->indices()->create($params);

        return $this->handler->handle($response, new CreateResponse);
    }

    public function remove(string $identifier): bool
    {
        $params = [
            'index' => $identifier
        ];

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->handler->handle($response, new DeleteResponse);
    }

    public function list(string $name = '*'): Collection
    {
        $params = [
            'index' => $name,
        ];

        $response = $this->elasticsearch->cat()->indices($params);

        return $this->handler->handle($response, new ListResponse);
    }

    public function get(string $name): Element
    {
        $params = [
            'index' => $name
        ];

        $response = $this->elasticsearch->indices()->get($params);

        return $this->handler->handle($response, new GetResponse);
    }
}
