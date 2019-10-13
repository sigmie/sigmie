<?php

namespace Ni\Elastic\Contract;

/**
 * Arrayable contract
 */
interface Arrayable
{
    /**
     * To array method
     *
     * @return array
     */
    public function toArray(): array;
}
