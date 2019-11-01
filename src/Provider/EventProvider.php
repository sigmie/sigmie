<?php

namespace Sigma\Provider;

use Sigma\Event\PreInsert;
use Sigma\Support\DependencyResolver;
use Sigma\Listener\ValidateMappings;

class EventProvider
{
    protected $listen = [
        PreInsert::class => [
            ValidateMappings::class
        ]
    ];

    protected function registerListeners()
    {
        $resolver = new DependencyResolver(get_object_vars($this));

        foreach ($this->listen as $event => $listeners) {
            foreach (array_unique($listeners) as $listener) {
                $instance = $resolver->instantiate($listener);
                $this->events()->addListener($event, [$instance, 'handle']);
            }
        }
    }
}
