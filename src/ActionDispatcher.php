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
    private $elasticsearch;

    private $eventDispatcher;

    public function __construct(Elasticsearch $elasticsearch, EventDispatcher $eventDispatcher)
    {
        $this->elasticsearch = $elasticsearch;
        $this->eventDispatcher = $eventDispatcher;
    }
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
