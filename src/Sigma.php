<?php

namespace Sigma;

use Sigma\Index\Manager;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Symfony\Component\EventDispatcher\EventDispatcher as EventManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as EventDispatcher;
use Sigma\ActionDispatcher;
use Sigma\Contract\Bootable;
use Sigma\Provider\SigmaProvider;

class Sigma extends SigmaProvider
{
    /**
     * Elastic search client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

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
     * Facade constructor
     *
     * @param Elasticsearch $elasticsearch
     * @param Manager $manager
     * @param EventManager $dispatcher
     */
    public function __construct(
        Elasticsearch $elasticsearch,
        EventManager $dispatcher,
        ActionDispatcher $actionDispatcher,
        ResponseHandler $responseHandler
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->events = $dispatcher;

        $this->registerListeners();

        $this->boot($actionDispatcher, $responseHandler);
    }

    /**
     * Facade create method
     *
     * @param Elasticsearch|null $elasticsearch
     * @param Manager|null $manager
     * @param EventDispatcher|null $events
     *
     * @return self
     */
    public static function create(
        ?Elasticsearch $elasticsearch = null,
        ?EventDispatcher $events = null
    ) {
        if ($elasticsearch === null) {
            $elasticsearch = ClientBuilder::create()->build();
        }

        if ($events === null) {
            $events = new EventManager();
        }

        $actionDispatcher = new ActionDispatcher($elasticsearch, $events);
        $responseHandler = new ResponseHandler();

        return new Sigma($elasticsearch, $events, $actionDispatcher, $responseHandler);
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

    public function bootElement(Bootable $bootable): Bootable
    {
        $bootable->boot($this->dispatcher, $this->handler);

        return $bootable;
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
}
