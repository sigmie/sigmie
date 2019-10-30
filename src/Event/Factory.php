<?php

namespace Sigma\Event;

use Sigma\Contract\Factory as FactoryInterface;
use ReflectionClass;
use Sigma\Event\Mapping;

class Factory
{
    public function create(array $classes =  [], $variables)
    {
        $bag = $this->createBag($variables);
        $subscibers = [];
        foreach ($classes as $class) {
            $subscibers[] = $this->initialize($class, $bag);
        }

        return $subscibers;
    }

    private function createBag($properties)
    {
        $bag = [];

        foreach ($properties as $instance) {
            if (is_object($instance) === false) {
                continue;
            }
            $bag[get_class($instance)] =  $instance;
        }

        return $bag;
    }

    private function initialize($class, array $bag)
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
            if (array_key_exists($type, $bag)) {
                $dependencies[]  =  $bag[$type];
            }
        }

        return new $class(...$dependencies);
    }
}
