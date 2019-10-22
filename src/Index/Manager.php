<?php

namespace Sigma\Index;

use Sigma\Element;
use Sigma\Collection;
use Sigma\Contract\ResponseHandler;
use Sigma\Contract\Manager as ManagerInterface;
use Sigma\Index\Action\Get as GetAction;
use Sigma\Index\Action\Insert as InsertAction;
use Sigma\Index\Action\Remove as RemoveAction;
use Sigma\Index\Action\Listing as ListingAction;
use Sigma\Index\Response\Get as GetResponse;
use Sigma\Index\Response\Insert as InsertResponse;
use Sigma\Index\Response\Remove as RemoveResponse;
use Sigma\Index\Response\Listing as ListingResponse;
use Sigma\Contract\ActionDispatcher;

class Manager implements ManagerInterface
{
    /**
     * Response handler
     *
     * @var ResponseHandler
     */
    private $handler;

    /**
     * Action dispatcher
     *
     * @var ActionDispatcher
     */
    private $dispatcher;

    /**
     * Constructor
     *
     * @param ActionDispatcher $dispatcher
     * @param ResponseHandler $handler
     */
    public function __construct(ActionDispatcher $dispatcher, ResponseHandler $handler)
    {
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;
    }

    /**
     * Dispatch the index insert action and
     * pass the response to the handler
     *
     * @param Element $index
     *
     * @return boolean
     */
    public function insert(Element $index): bool
    {
        $response = $this->dispatcher->dispatch($index, new InsertAction);

        return $this->handler->handle($response, new InsertResponse);
    }

    /**
     * Dispatch the index remove action and
     * pass the response to the handler
     *
     * @param string $identifier
     *
     * @return boolean
     */
    public function remove(string $identifier): bool
    {
        $response = $this->dispatcher->dispatch($identifier, new RemoveAction);

        return $this->handler->handle($response, new RemoveResponse);
    }

    /**
     * Dispatch the index listing action and
     * pass the response to the handler
     *
     * @param string $name
     *
     * @return Collection
     */
    public function list(string $name = '*'): Collection
    {
        $response = $this->dispatcher->dispatch($name, new ListingAction);

        return $this->handler->handle($response, new ListingResponse);
    }

    /**
     * Dispatch the index get action and
     * pass the response to the handler
     *
     * @param string $name
     *
     * @return Element
     */
    public function get(string $name): Element
    {
        $response = $this->dispatcher->dispatch($name, new GetAction);

        return $this->handler->handle($response, new GetResponse);
    }
}
