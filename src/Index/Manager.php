<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Element;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\ResponseHandler;
use Ni\Elastic\Contract\Manager as ManagerInterface;
use Ni\Elastic\Index\Action\Get as GetAction;
use Ni\Elastic\Index\Action\Create as CreateAction;
use Ni\Elastic\Index\Action\Remove as RemoveAction;
use Ni\Elastic\Index\Action\Listing as ListingAction;
use Ni\Elastic\Index\Response\Get as GetResponse;
use Ni\Elastic\Index\Response\Create as CreateResponse;
use Ni\Elastic\Index\Response\Remove as RemoveResponse;
use Ni\Elastic\Index\Response\Listing as ListingResponse;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\ActionDispatcher;

class Manager implements ManagerInterface
{
    private $handler;

    private $dispatcher;

    public function __construct(ActionDispatcher $dispatcher, ResponseHandler $handler)
    {
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;
    }

    public function create(Element $index): bool
    {
        $response = $this->dispatcher->dispatch($index, new CreateAction);

        return $this->handler->handle($response, new CreateResponse);
    }

    public function remove(string $identifier): bool
    {
        $response = $this->dispatcher->dispatch($identifier, new RemoveAction);

        return $this->handler->handle($response, new RemoveResponse);
    }

    public function list(string $name = '*'): Collection
    {
        $response = $this->dispatcher->dispatch($name, new ListingAction);

        return $this->handler->handle($response, new ListingResponse);
    }

    public function get(string $name): Element
    {
        $response = $this->dispatcher->dispatch($name, new GetAction);

        return $this->handler->handle($response, new GetResponse);
    }
}
