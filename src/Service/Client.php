<?php

namespace Ni\Elastic\Service;

use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Builder;
use Ni\Elastic\Manager;
use Ni\Elastic\Index\IndexManager;
use Symfony\Component\EventDispatcher\EventDispatcher as EventManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

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
    public function __construct(
        Elasticsearch $elasticsearch,
        Manager $manager,
        EventManager $dispatcher
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->manager = $manager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Client facade generator
     *
     * @param Elasticsearch|null $elasticsearch
     * @param Builder|null $manager
     *
     * @return Client
     */
    public static function create(
        ?Elasticsearch $elasticsearch = null,
        ?Manager $manager = null,
        ?EventDispatcher $dispatcher = null
    ) {
        if ($elasticsearch === null) {
            $elasticsearch = ClientBuilder::create()->build();
        }

        if ($manager === null) {
            $builder = new ManagerBuilder($elasticsearch);
        }

        if ($dispatcher === null) {
            $dispatcher = new EventManager();
        }

        $builder->setEventDispatcher($dispatcher);

        $manager = $builder->build();

        return new Client($elasticsearch, $manager, $dispatcher);
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

    public function events(): EventManager
    {
        return $this->dispatcher;
    }

    public function isConnected(): bool
    {
        $this->connected = $this->elasticsearch->ping();

        return $this->connected;
    }

    /**
     * Build an return the manager instance
     *
     * @return Manager
     */
    public function manage(): Manager
    {
        return $this->manager;
    }
}
