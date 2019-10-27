<?php

namespace Sigma\Common;

use Sigma\Contract\ResponseHandler;
use Sigma\Contract\ActionDispatcher;

trait Bootable
{
    private $booted = false;

    private $handler = null;

    private $dispatcher = null;

    public function boot(ActionDispatcher $dispatcher, ResponseHandler $handler)
    {
        $this->dispatcher = $dispatcher;
        $this->handler = $handler;

        $this->booted = true;
    }
}
