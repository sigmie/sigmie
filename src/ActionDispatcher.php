<?php

namespace Ni\Elastic;

use Ni\Elastic\Contract\Action;
use Ni\Elastic\Contract\ActionDispatcher as ActionDispatcherInterface;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Subscribable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

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
        $isSubscribable =  $action instanceof Subscribable;

        if ($isSubscribable) {
            $beforeEvent = $action->beforeEvent();
            $afterEvent = $action->afterEvent();
        }

        if ($this->eventDispatcher->hasListeners($beforeEvent)) {
            $this->eventDispatcher->dispatch($beforeEvent);
        }

        $params = $action->prepare($data);

        $response = $action->execute($this->elasticsearch, $params);

        if ($this->eventDispatcher->hasListeners($afterEvent)) {
            $this->eventDispatcher->dispatch($afterEvent);
        }

        return $response;
    }
}
