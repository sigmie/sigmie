<?php

declare(strict_types=1);


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
    public function dispatch(Action $action, ...$data): array
    {
        $beforeEvent = null;
        $afterEvent = null;

        if ($action instanceof Subscribable) {
            $beforeEvent = $action->preEvent();
            $afterEvent = $action->postEvent();
        }

        if ($this->eventDispatcher->hasListeners($beforeEvent) && $beforeEvent !== null) {
            $this->eventDispatcher->dispatch(new $beforeEvent($data), $beforeEvent);
        }

        $params = $action->prepare(...$data);

        $response = $action->execute($this->elasticsearch, $params);

        if ($this->eventDispatcher->hasListeners($afterEvent) && $afterEvent !== null) {
            dump($response);
            die();
            $this->eventDispatcher->dispatch(new $afterEvent($response), $afterEvent);
        }

        return $response;
    }
}
