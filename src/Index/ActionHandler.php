<?php

namespace Ni\Elastic\Index;

use Ni\Elastic\Contract\ResponseHandler;
use Ni\Elastic\Contract\Response;
use Symfony\Component\EventDispatcher\EventDispatcherInterface as EventDispatcher;

class ActionHandler implements ResponseHandler
{
    private $dispatcher;

    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }
    public function handle(array $content, Response $response)
    {
        // $event = $response->afterEvent();

        // if ($this->dispatcher->hasListeners($event)) {
        //     $this->dispatcher->dispatch($event);
        // }

        return $response->result($content);
    }
}
