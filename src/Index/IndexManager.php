<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Element;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Handler;
use Ni\Elastic\Contract\Manager;
use Ni\Elastic\Index\Actions\Get;
use Ni\Elastic\Index\Actions\Create;
use Ni\Elastic\Index\Actions\Remove;
use Ni\Elastic\Index\Actions\Listing;
use Elasticsearch\Client as Elasticsearch;

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

    public function create(Element $index): bool
    {
        $action = new Create();

        $params = $action->prepare($index);

        $response = $this->elasticsearch->indices()->create($params);

        return $this->handler->handle($response, $action);
    }

    public function remove(string $identifier): bool
    {
        $action = new Remove();

        $params = $action->prepare($identifier);

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->handler->handle($response, $action);
    }

    public function list(string $name = '*'): Collection
    {
        $action = new Listing();

        $params = $action->prepare($name);

        $response = $this->elasticsearch->cat()->indices($params);

        return $this->handler->handle($response, $action);
    }

    public function get(string $name): Element
    {
        $action = new Get();

        $params = $action->prepare($name);

        $response = $this->elasticsearch->indices()->get($params);

        return $this->handler->handle($response, $action);
    }
}
