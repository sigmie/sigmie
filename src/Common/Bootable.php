<?php

namespace Sigma\Common;

use Sigma\Contract\Action;
use Sigma\Contract\ResponseHandler;
use Sigma\Contract\ActionDispatcher;
use Sigma\Contract\Response;

trait Bootable
{
    private $booted = false;

    /**
     * Response handler
     *
     * @var ResponseHandler
     */
    private $handler;

    /**
     * Action dispatcher
     *
     * @var ActionDispatcher
     */
    private $dispatcher;

    public function isBooted() : bool
    {
        return $this->booted;
    }

    public function boot(ActionDispatcher $dispatcher, ResponseHandler $handler)
    {
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;

        $this->booted = true;
    }

    public function execute($params, Action $action, Response $response)
    {
        $rawResponse = $this->dispatcher->dispatch($params, $action);

        return $this->handler->handle($rawResponse, $response);
    }
}
