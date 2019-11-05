<?php

namespace Sigma\Common;

use Sigma\Contract\Action;
use Sigma\Contract\ResponseHandler;
use Sigma\Contract\ActionDispatcher;
use Sigma\Contract\Response;
use Sigma\Exception\NotBootedException;

trait Bootable
{
    protected $booted = false;

    /**
     * Response handler
     *
     * @var ResponseHandler
     */
    protected $handler;

    /**
     * Action dispatcher
     *
     * @var ActionDispatcher
     */
    protected $dispatcher;

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function boot(ActionDispatcher $dispatcher, ResponseHandler $handler)
    {
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;

        $this->booted = true;
    }

    public function execute(Action $action, Response $response, ...$params)
    {
        if ($this->dispatcher === null || $this->handler === null) {
            throw new NotBootedException;
        }

        $rawResponse = $this->dispatcher->dispatch( $action, ...$params);

        return $this->handler->handle($rawResponse, $response);
    }
}
