<?php

namespace Ni\Elastic\Manager;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\Manager as IndexManager;
use Ni\Elastic\Manager\Manager;
use Ni\Elastic\ActionDispatcher;
use Ni\Elastic\Contract\ActionDispatcher as ActionDispatcherInterface;
use Ni\Elastic\Contract\ResponseHandler as ResponseHandlerInterface;
use Ni\Elastic\ResponseHandler;
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

    private function responseHandler(): ResponseHandlerInterface
    {
        if ($this->responseHandler instanceof ResponseHandlerInterface) {
            return $this->responseHandler;
        }

        $this->responseHandler = new ResponseHandler();

        return $this->responseHandler;
    }

    private function eventDispatcher(): EventDispatcherInterface
    {
        if ($this->eventDispatcher instanceof EventDispatcherInterface) {
            return $this->eventDispatcher;
        }

        $this->eventDispatcher = new EventDispatcher();

        return $this->eventDispatcher;
    }

    private function actionDispatcher(): ActionDispatcherInterface
    {
        if ($this->actionDispatcher instanceof ActionDispatcherInterface) {
            return $this->actionDispatcher;
        }

        $this->actionDispatcher = new ActionDispatcher($this->elasticsearch, $this->eventDispatcher());

        return $this->actionDispatcher;
    }

    public function newIndexManager(): IndexManager
    {
        $actionDispatcher = $this->actionDispatcher();
        $responseHandler = $this->responseHandler();

        $manager = new IndexManager($actionDispatcher, $responseHandler);

        return $manager;
    }

    /**
     * Get the value of responseHandler
     */
    public function getResponseHandler()
    {
        return $this->responseHandler;
    }

    /**
     * Set the value of responseHandler
     *
     * @return  self
     */
    public function setResponseHandler($responseHandler)
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }

    /**
     * Get the value of eventDispatcher
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Set the value of eventDispatcher
     *
     * @return  self
     */
    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Get the value of actionDispatcher
     */
    public function getActionDispatcher()
    {
        return $this->actionDispatcher;
    }

    /**
     * Set the value of actionDispatcher
     *
     * @return  self
     */
    public function setActionDispatcher($actionDispatcher)
    {
        $this->actionDispatcher = $actionDispatcher;

        return $this;
    }
}
