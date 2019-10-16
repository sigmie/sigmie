<?php

namespace Sigma;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;

abstract class Collection implements IteratorAggregate, Countable, ArrayAccess
{
    /**
     * Collection elements
     *
     * @var array
     */
    private $elements;

    /**
     * Collection constructor
     *
     * @param array $elements
     */
    public function __construct(array $elements)
    {
        $this->elements = $elements;
    }

    /**
     * Set offset method
     *
     * @param string $offset
     * @param Element $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->elements[] = $value;
            return;
        }

        $this->elements[$offset] = $value;
    }

    /**
     * Offset exists method
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->elements[$offset]);
    }

    /**
     * Unset offset method
     *
     * @param string $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }

    /**
     * Get offset
     *
     * @param string $offset
     *
     * @return Element|null
     */
    public function offsetGet($offset): ?Element
    {
        return isset($this->elements[$offset]) ? $this->elements[$offset] : null;
    }

    /**
     * Iterator method
     *
     * @return ArrayIterator
     */
    public function getIterator() : ArrayIterator
    {
        return new ArrayIterator($this->elements);
    }

    /**
     * Count method
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->elements);
    }

    /**
     * First element method
     *
     * @return Element
     */
    public function first(): Element
    {
        return $this->elements[0];
    }
}
