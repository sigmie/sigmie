<?php

namespace Sigma\Support;

use ReflectionClass;

class DependencyResolver
{
    private $bag = [];

    public function __construct($instanes)
    {
        $this->bag = $this->createBag($instanes);
    }

    public function instantiate($class)
    {
        $reflection = new ReflectionClass($class);
        $parameters = [];
        $constructor = $reflection->getConstructor();

        if ($constructor !== null) {
            $parameters = $constructor->getParameters();
        }

        $dependencies = [];

        foreach ($parameters as $param) {
            $type =  $param->getType()->__toString();
            if (array_key_exists($type, $this->bag)) {
                $dependencies[]  =  $this->bag[$type];
            }
        }

        return new $class(...$dependencies);
    }

    private function createBag(array $instances)
    {
        $bag = [];

        foreach ($instances as $instance) {
            if (is_object($instance) === false) {
                continue;
            }
            $bag[get_class($instance)] =  $instance;
        }

        return $bag;
    }
}
