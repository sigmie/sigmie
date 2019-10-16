<?php

namespace Sigma\Manager;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Index\Manager as IndexManager;
use Sigma\Manager\Manager;
use Sigma\ActionDispatcher;
use Sigma\Contract\ActionDispatcher as ActionDispatcherInterface;
use Sigma\Contract\ResponseHandler as ResponseHandlerInterface;
use Sigma\ResponseHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ManagerBuilder
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Response handler
     *
     * @var ResponseHandlerInterface
     */
    private $responseHandler = null;

    /**
     * Event dispatcher
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher = null;

    /**
     * Action dispatcher
     *
     * @var ActionDispatcherInterface
     */
    private $actionDispatcher = null;

    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Initialize the manager class
     *
     * @return Manager
     */
    public function build(): Manager
    {
        $manager = new Manager();
        $manager->index = $this->newIndexManager();

        return $manager;
    }

    /**
     * Response handler method
     *
     * @return ResponseHandlerInterface
     */
    private function responseHandler(): ResponseHandlerInterface
    {
        if ($this->responseHandler instanceof ResponseHandlerInterface) {
            return $this->responseHandler;
        }

        $this->responseHandler = new ResponseHandler();

        return $this->responseHandler;
    }

    /**
     * Event dispatcher method
     *
     * @return EventDispatcherInterface
     */
    private function eventDispatcher(): EventDispatcherInterface
    {
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            return $this->eventDispatcher;
        }

        $this->eventDispatcher = new EventDispatcher();

        return $this->eventDispatcher;
    }

    /**
     * Action dispatcher method
     *
     * @return ActionDispatcherInterface
     */
    private function actionDispatcher(): ActionDispatcherInterface
    {
        if ($this->actionDispatcher instanceof ActionDispatcherInterface) {
            return $this->actionDispatcher;
        }

        $this->actionDispatcher = new ActionDispatcher($this->elasticsearch, $this->eventDispatcher());

        return $this->actionDispatcher;
    }

    /**
     * Index manager initialization
     *
     * @return IndexManager
     */
    public function newIndexManager(): IndexManager
    {
        $actionDispatcher = $this->actionDispatcher();
        $responseHandler = $this->responseHandler();

        $manager = new IndexManager($actionDispatcher, $responseHandler);

        return $manager;
    }

    /**
     * Get action dispatcher
     *
     * @return  ActionDispatcherInterface
     */ 
    public function getActionDispatcher()
    {
        return $this->actionDispatcher;
    }

    /**
     * Set action dispatcher
     *
     * @param  ActionDispatcherInterface  $actionDispatcher  Action dispatcher
     *
     * @return  self
     */ 
    public function setActionDispatcher(ActionDispatcherInterface $actionDispatcher)
    {
        $this->actionDispatcher = $actionDispatcher;

        return $this;
    }

    /**
     * Get event dispatcher
     *
     * @return  EventDispatcherInterface
     */ 
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Set event dispatcher
     *
     * @param  EventDispatcherInterface  $eventDispatcher  Event dispatcher
     *
     * @return  self
     */ 
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Get response handler
     *
     * @return  ResponseHandlerInterface
     */ 
    public function getResponseHandler()
    {
        return $this->responseHandler;
    }

    /**
     * Set response handler
     *
     * @param  ResponseHandlerInterface  $responseHandler  Response handler
     *
     * @return  self
     */ 
    public function setResponseHandler(ResponseHandlerInterface $responseHandler)
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }
}
