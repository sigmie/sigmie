<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;

class Client
{

    /**
     * Elasticseach host
     *
     * @var string
     */
    private $host;

    /**
     * Elasticsearch port
     *
     * @var string
     */
    private $port;

    /**
     * Elastic search client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    public function __construct(string $host = '127.0.0.1', string $port = '9200', ?Elasticsearch $elasticsearch = null)
    {
        $this->host = $host;
        $this->port = $port;

        if ($elasticsearch !== null) {
            $this->elasticsearch = $elasticsearch;
        }
    }

    /**
     * Get elasticsearch port
     *
     * @return  string
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Set elasticsearch port
     *
     * @param  string  $port  Elasticsearch port
     *
     * @return  self
     */
    public function setPort(string $port)
    {
        $this->port = $port;

        return $this;
    }

    /**
     * Get elasticseach host
     *
     * @return  string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Set elasticseach host
     *
     * @param  string  $host  Elasticseach host
     *
     * @return  self
     */
    public function setHost(string $host)
    {
        $this->host = $host;

        return $this;
    }
    /**
     * Get elastic search client
     *
     * @return  Elasticsearch
     */
    public function getElasticsearch()
    {
        if ($this->elasticsearch === null) {
            $this->elasticsearch();
        }

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

    public function elasticsearch(): Elasticsearch
    {
        if ($this->elasticsearch instanceof Elasticsearch) {
            return $this->elasticsearch;
        }

        $this->elasticsearch = $this->build();

        return $this->elasticsearch;
    }

    private function build(): Elasticsearch
    {
        return ClientBuilder::create()->build();
    }
}
