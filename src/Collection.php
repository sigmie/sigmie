<?php

namespace Ni\Elastic;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Ni\Elastic\Miscellaneous\Mapping;

abstract class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    use Mapping;

    private $elements;

    public function __construct(array $list)
    {
        $this->elements = $list;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->elements[] = $value;
            return;
        }

        $this->elements[$offset] = $value;
    }

    public function offsetExists($offset)
    {
        return isset($this->elements[$offset]);
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }

    public function offsetGet($offset)
    {
        return isset($this->elements[$offset]) ? $this->elements[$offset] : null;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->elements);
    }

    public function count()
    {
        return count($this->elements);
    }
}
