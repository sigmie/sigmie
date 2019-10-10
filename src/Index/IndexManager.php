<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Element;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\ResponseHandler;
use Ni\Elastic\Contract\Manager;
use Ni\Elastic\Index\Actions\Get as GetAction;
use Ni\Elastic\Index\Actions\Create as CreateAction;
use Ni\Elastic\Index\Actions\Remove as RemoveAction;
use Ni\Elastic\Index\Actions\Listing as ListingAction;
use Ni\Elastic\Index\Response\Get as GetResponse;
use Ni\Elastic\Index\Response\Create as CreateResponse;
use Ni\Elastic\Index\Response\Remove as RemoveResponse;
use Ni\Elastic\Index\Response\Listing as ListingResponse;
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

    public function __construct(Elasticsearch $elasticsearch, ResponseHandler $handler)
    {
        $this->elasticsearch = $elasticsearch;
        $this->handler = $handler;
    }

    public function create(Element $index): bool
    {
        $action = new CreateAction();

        $params = $action->prepare($index);

        $response = $this->elasticsearch->indices()->create($params);

        return $this->handler->handle($response, new CreateResponse);
    }

    public function remove(string $identifier): bool
    {
        $action = new RemoveAction();

        $params = $action->prepare($identifier);

        $response = $this->elasticsearch->indices()->delete($params);

        return $this->handler->handle($response, new RemoveResponse);
    }

    public function list(string $name = '*'): Collection
    {
        $action = new ListingAction();

        $params = $action->prepare($name);

        $response = $this->elasticsearch->cat()->indices($params);

        return $this->handler->handle($response, new ListingResponse);
    }

    public function get(string $name): Element
    {
        $action = new GetAction();

        $params = $action->prepare($name);

        $response = $this->elasticsearch->indices()->get($params);

        return $this->handler->handle($response, new GetResponse);
    }
}
