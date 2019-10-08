<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Builder;
use Ni\Elastic\Index\IndexHandler;
use Ni\Elastic\Index\IndexManager;

class Client
{
    /**
     * Elastic search client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Manager
     *
     * * @var Manager
     */
    private $manager;

    /**
     * Connected flag
     *
     * @var bool
     */
    private $connected;

    /**
     * Class constructor
     *
     * @param Elasticsearch $elasticsearch
     */
    public function __construct(Elasticsearch $elasticsearch, IndexManager $manager)
    {
        $this->elasticsearch = $elasticsearch;
        $this->manager = $manager;
    }

    /**
     * Client facade generator
     *
     * @param Elasticsearch|null $elasticsearch
     * @param Builder|null $manager
     *
     * @return Client
     */
    public static function create(?Elasticsearch $elasticsearch = null, ?Builder $manager = null)
    {
        if ($elasticsearch === null) {
            $elasticsearch = ClientBuilder::create()->build();
        }

        if ($manager === null) {
            $manager = (new ManagerBuilder($elasticsearch))->build();
        }

        return new Client($elasticsearch, $manager);
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
        return $this->elasticsearch;
    }

    public function isConnected(): bool
    {
        $this->connected = $this->elasticsearch->ping();

        return $this->connected;
    }

    /**
     * Build an return the manager instance
     *
     * @return IndexManager
     */
    public function manage(): IndexManager
    {
        return $this->manager;
    }
}
