<?php

namespace Sigma;

use Sigma\Index\Manager;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client as Elasticsearch;
use Symfony\Component\EventDispatcher\EventDispatcher as EventManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface as EventDispatcher;
use Sigma\ActionDispatcher;
use Sigma\Event\Factory;
use Sigma\Event\Registry;
use Sigma\Common\InteractsWithIndex;

class Sigma
{
    use InteractsWithIndex;

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

        $subcribers = (new Factory())->create(Registry::subscribers(), [$actionDispatcher, $responseHandler, $elasticsearch]);

        foreach ($subcribers as $subcriber) {
            $events->addSubscriber($subcriber);
        }

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
