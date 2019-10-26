<?php

namespace Sigma;

abstract class Element
{
    public function __construct()
    {
    }

    public function __set($name, $value)
    {
        $name = ltrim($name, '_');

        if (property_exists($this, $name)) {
            $this->$name = $value;
        }

        return $this;
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }
}
