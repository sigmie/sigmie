<?php

namespace Ni\Elastic\Service;

use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Index\BaseIndex;
use Ni\Elastic\Type\BaseType;

class Manager
{
    /**
     * Index instance
     *
     * @var BaseIndex
     */
    private $index = null;

    /**
     * Type instance
     * 
     * @var BaseType
     */
    private $type = null;

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
     * @param  BaseIndex  $index  Index instance
     *
     * @return  self
     */
    public function setIndex(BaseIndex $index)
    {
        $this->index = $index;

        return $this;
    }

    public function index(): BaseIndex
    {
        return $this->index;
    }

    public function Type(): BaseType
    {
        return $this->type;
    }

    /**
     * Set type instance
     *
     * @param  BaseType  $type  Type instance
     *
     * @return  self
     */
    public function setType(BaseType $type)
    {
        $this->type = $type;

        return $this;
    }
}
