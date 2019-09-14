<?php

namespace niElastic\Service;

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

    public function __construct(string $host = '127.0.0.1', string $port = '9200')
    {
        $this->host = $host;
        $this->port = $port;
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
}
