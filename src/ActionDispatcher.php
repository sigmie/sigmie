<?php

namespace Sigma;

use Elasticsearch\Client as Elasticsearch;
use Sigma\Contract\Action;
use Sigma\Contract\ActionDispatcher as ActionDispatcherInterface;
use Sigma\Contract\Subscribable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;
use Symfony\Component\EventDispatcher\GenericEvent;

class ActionDispatcher implements ActionDispatcherInterface
{
    /**
     * Elasticsearch client
     *
     * @var Elasticsearch
     */
    private $elasticsearch;

    /**
     * Event dispatcher
     *
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * Constructor
     *
     * @param Elasticsearch $elasticsearch
     * @param EventDispatcher $eventDispatcher
     */
    public function __construct(Elasticsearch $elasticsearch, EventDispatcher $eventDispatcher)
    {
        $this->elasticsearch = $elasticsearch;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Action dispatch method
     *
     * @param Element|Collection|string $data
     * @param Action $action
     *
     * @return array
     */
    public function dispatch($data, Action $action): array
    {
        $beforeEvent = null;
        $afterEvent = null;

        if ($action instanceof Subscribable) {
            $beforeEvent = $action->beforeEvent();
            $afterEvent = $action->afterEvent();
        }

        if ($this->eventDispatcher->hasListeners($beforeEvent)) {
            $this->eventDispatcher->dispatch($beforeEvent, new GenericEvent($data));
        }

        $params = $action->prepare($data);

        $response = $action->execute($this->elasticsearch, $params);

        if ($this->eventDispatcher->hasListeners($afterEvent)) {
            $this->eventDispatcher->dispatch($afterEvent, new GenericEvent($response));
        }

        return $response;
    }
}
