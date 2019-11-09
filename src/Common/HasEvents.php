<?php

declare(strict_types=1);


namespace Sigma\Common;

use ReflectionClass;
use ReflectionProperty;
use ReflectionMethod;

trait HasEvents
{
    public static function getSubscribedEvents()
    {
        $reflection = new ReflectionClass(self::class);
        $public = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
        $static = $reflection->getMethods(ReflectionMethod::IS_STATIC);
        $methods = array_diff($public, $static);

        $events = array_map(function ($refMethod) {
            return    $refMethod->getName();
        }, $methods);

        return array_combine($events, $events);
    }
}
