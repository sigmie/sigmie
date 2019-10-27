<?php

namespace Sigma;

use Sigma\Index\Manager;
use Sigma\Manager\ManagerBuilder;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Symfony\Component\EventDispatcher\EventDispatcher as EventManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as EventDispatcher;
use Sigma\Common\Bootable;
use Sigma\Contract\ActionDispatcher;

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
     * Event manager
     *
     * @var EventManager
     */
    private $events;

    /**
     * Action dispatcher
     *
     * @var ActionDispatcher
     */
    private $dispatcher;

    /**
     * Reponse handler
     *
     * @var ResponseHandler
     */
    private $handler;

    /**
     * Facade constructor
     *
     * @param Elasticsearch $elasticsearch
     * @param Manager $manager
     * @param EventManager $dispatcher
     */
    public function __construct(
        Elasticsearch $elasticsearch,
        Manager $manager,
        EventManager $dispatcher
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->events = $dispatcher;
        $this->manager = $manager;
    }

    /**
     * Facade create method
     *
     * @param Elasticsearch|null $elasticsearch
     * @param Manager|null $manager
     * @param EventDispatcher|null $dispatcher
     *
     * @return self
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
     * Official Elasticsearch
     * library entry point
     *
     * @return Elasticsearch
     */
    public function elasticsearch(): Elasticsearch
    {
        return $this->elasticsearch;
    }

    /**
     * Entry point for managing
     * the application events
     *
     * @return EventManager
     */
    public function events(): EventManager
    {
        return $this->events;
    }

    /**
     * Helper method to check if the client
     * is connected to Elasticsearch
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        $this->connected = $this->elasticsearch->ping();

        return $this->connected;
    }

    /**
     * Entry point for Sigma
     *
     * @return Manager
     */
    public function index(): Manager
    {
        return $this->manager;
    }

    public function boot(Element $bootable): Element
    {
        $bootable->boot($this->dispatcher, $this->handler);

        return $bootable;
    }
}
