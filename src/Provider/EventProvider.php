<?php

namespace Sigma\Provider;

use Sigma\Support\DependencyResolver;
use Sigma\Listener\ValidateMappings;
use Sigma\Event\Index\PreInsert as IndexPreInsert;
use Sigma\Event\Document\PreInsert as DocumentPreInsert;
use Sigma\Event\Index\PostInsert as IndexPostInsert;
use Sigma\Event\Document\PostInsert as DocumentPostInsert;

class EventProvider
{
    protected $listen = [
        IndexPreInsert::class => [],
        IndexPostInsert::class => [],
        DocumentPreInsert::class => [
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
