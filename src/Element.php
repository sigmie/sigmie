<?php

declare(strict_types=1);


namespace Sigma;

use Sigma\Common\Bootable;
use Sigma\Contract\Arrayable;

abstract class Element
{
    use Bootable;

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
