<?php

namespace Sigma\Contract;

use Sigma\Element;
use Sigma\Collection;

/**
 * Manager contract
 */
interface Manager
{
    /**
     * Insert method
     *
     * @param Element $element
     *
     * @return boolean
     */
    public function insert(Element $element): bool;

    /**
     * Remove method
     *
     * @param string $identifier
     *
     * @return boolean
     */
    public function remove(string $identifier): bool;

    /**
     * List method
     *
     * @param string $name
     *
     * @return Collection
     */
    public function list(string $name): Collection;

    /**
     * Get method
     *
     * @param string $identifier
     *
     * @return Element
     */
    public function get(string $identifier): Element;
}
