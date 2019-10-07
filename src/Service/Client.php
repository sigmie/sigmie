<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Builder;
use Ni\Elastic\Index\IndexHandler;
use Ni\Elastic\Index\Manager;

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
     * * @var Manager
     */
    private $manager;

    /**
     * Class constructor
     *
     * @param Elasticsearch $elasticsearch
     */
    public function __construct(Elasticsearch $elasticsearch, Manager $manager)
    {
        $this->elasticsearch = $elasticsearch;
        $this->manager = $manager;
    }

    /**
     * Client facade generator
     *
     * @param Elasticsearch|null $elasticsearch
     * @param Builder|null $managerBuilder
     * 
     * @return Client
     */
    public static function create(?Elasticsearch $elasticsearch = null, ?Builder $managerBuilder = null)
    {
        if ($elasticsearch === null) {
            $elasticsearch = ClientBuilder::create()->build();
        }

        if ($managerBuilder === null) {
            $managerBuilder = new ManagerBuilder($elasticsearch);
        }

        $manager = $managerBuilder->build();

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
        return $this->elasticsearch->ping();
    }

    /**
     * Build an return the manager instance
     *
     * @return Manager
     */
    public function manage(): Manager
    {
        if ($this->manager instanceof Manager) {
            return $this->manager;
        }

        $this->manager = (new ManagerBuilder($this->elasticsearch()))->build();

        return $this->manager;
    }
}
