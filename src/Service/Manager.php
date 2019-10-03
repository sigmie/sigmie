<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\IndexBase;

class Manager
{
    /**
     * Index instance
     *
     * @var IndexBase
     */
    private $index = null;

    /**
     * Elasticsearch Client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Get elasticsearch Client
     *
     * @return  Elasticsearch
     */
    public function getElasticsearch()
    {
        return $this->elasticsearch;
    }

    /**
     * Set elasticsearch Client
     *
     * @param  Elasticsearch  $elasticsearch  Elasticsearch Client
     *
     * @return  self
     */
    public function setElasticsearch(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;

        return $this;
    }

    /**
     * Set index instance
     *
     * @param  IndexBase  $index  Index instance
     *
     * @return  self
     */
    public function setIndex(IndexBase $index)
    {
        $this->index = $index;

        return $this;
    }

    public function index(): IndexBase
    {
        return $this->index;
    }
}
