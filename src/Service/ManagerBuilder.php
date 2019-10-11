<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\ActionDispatcher;
use Ni\Elastic\Index\Manager;
use Ni\Elastic\Response\ResponseFactory;
use Ni\Elastic\Index\IndexBase;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\ResponseHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ManagerBuilder
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    private $responseHandler = null;

    private $eventDispatcher = null;

    private $actionDispatcher = null;

    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function build(): Manager
    {
        if ($this->eventDispatcher === null) {
            $this->eventDispatcher = new EventDispatcher();
        }

        if ($this->responseHandler === null) {
            $this->responseHandler = new ResponseHandler();
        }

        if ($this->actionDispatcher === null) {
            $this->actionDispatcher = new ActionDispatcher($this->elasticsearch, $this->eventDispatcher);
        }

        $manager = new Manager($this->actionDispatcher, $this->responseHandler);

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
