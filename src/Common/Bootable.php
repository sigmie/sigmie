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

    public function execute($params, Action $action, Response $response)
    {
        if ($this->dispatcher === null || $this->handler) {
            throw new NotBootedException;
        }

        $rawResponse = $this->dispatcher->dispatch($params, $action);

        return $this->handler->handle($rawResponse, $response);
    }
}
