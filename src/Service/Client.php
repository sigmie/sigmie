<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;

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
    public function __construct(Elasticsearch $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    public static function create(?Elasticsearch $elasticsearch = null)
    {
        if ($elasticsearch === null) {
            $elasticsearch = ClientBuilder::create()->build();
        }

        return new Client($elasticsearch);
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
