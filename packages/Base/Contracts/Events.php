<?php

declare(strict_types=1);

namespace Sigmie\Base\Contracts;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

trait Events
{
    protected EventDispatcherInterface $events;

    protected function events(): EventDispatcherInterface
    {
        if (isset($this->events)) {
            return $this->events;
        }
        
        $this->events = new EventDispatcher;

        return $this->events;
    }
}
