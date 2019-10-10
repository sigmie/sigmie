<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Contract\Handler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

class ResponseHandler implements Handler
{
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    public function handle($content, $response)
    {
        $event = $response->afterEvent();

        if ($this->dispatcher->hasListeners($event)) {
            $this->dispatcher->dispatch($event);
        }
        // TODO add event triggering after
        return $response->result($content);
    }
}
