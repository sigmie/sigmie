<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\IndexManager;
use Ni\Elastic\Response\ResponseFactory;
use Ni\Elastic\Index\IndexBase;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\ActionHandler;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ManagerBuilder
{
    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    private $handler = null;

    private $dispatcher = null;

    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public function build(): IndexManager
    {
        if ($this->dispatcher === null) {
            $this->dispatcher = new EventDispatcher();
        }

        if ($this->handler === null) {
            $this->handler = new ActionHandler($this->dispatcher);
        }

        $manager = new IndexManager($this->elasticsearch, $this->handler);

        return $manager;
    }

    /**
     * Set the value of handler
     *
     * @return  self
     */
    public function setHandler($handler)
    {
        $this->handler = $handler;

        return $this;
    }

    /**
     * Get the value of dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set the value of dispatcher
     *
     * @return  self
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }
}
