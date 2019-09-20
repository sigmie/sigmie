<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;

class Client
{
    /**
     * Hosts
     *
     * @var array
     */
    private $hosts;

    /**
     * Elastic search client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Client builder
     *
     * @var ClientBuilder
     */
    private $builder;

    public function __construct(array $hosts = [], ?Elasticsearch $elasticsearch = null, ?ClientBuilder $builder = null)
    {
        $this->hosts = $hosts;

        if ($elasticsearch !== null) {
            $this->elasticsearch = $elasticsearch;
        }

        if ($builder !== null) {
            $this->builder = $builder;
        }

        $this->elasticsearch();
    }

    /**
     * Get elasticseach hosts
     *
     * @return  string
     */
    public function getHosts()
    {
        return $this->hosts;
    }

    /**
     * Set elasticseach hosts
     *
     * @param array $host Elasticseach hosts
     *
     * @return  self
     */
    public function setHosts(array $hosts)
    {
        $this->hosts = $hosts;

        return $this;
    }

    /**
     * Get elastic search client
     *
     * @return  Elasticsearch
     */
    public function getElasticsearch()
    {

        return $this->elasticsearch;
    }

    /**
     * Set elastic search client
     *
     * @param  Elasticsearch  $elasticsearch  Elastic search client
     *
     * @return  self
     */
    public function setElasticsearch(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;

        return $this;
    }

    /**
     * Build the Elasticsearch client if the
     * elasticsearch is not initialized yet
     * and returns the instance
     *
     * @return Elasticsearch
     */
    public function elasticsearch(): Elasticsearch
    {
        if ($this->elasticsearch instanceof Elasticsearch) {
            return $this->elasticsearch;
        }

        $this->elasticsearch = $this->build();

        return $this->elasticsearch;
    }

    /**
     * Build the Elasticsearch client
     *
     * @return Elasticsearch
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function build(): Elasticsearch
    {
        if ($this->builder instanceof ClientBuilder) {
            return $this->builder
                ->setHosts($this->hosts)
                ->build();
        }

        $this->builder = ClientBuilder::create();

        return $this->build();
    }

    /**
     * Get client builder
     *
     * @return  ClientBuilder
     */
    public function getBuilder()
    {
        return $this->builder;
    }

    /**
     * Set client builder
     *
     * @param  ClientBuilder  $builder  Client builder
     *
     * @return  self
     */
    public function setBuilder(ClientBuilder $builder)
    {
        $this->builder = $builder;

        return $this;
    }
}
