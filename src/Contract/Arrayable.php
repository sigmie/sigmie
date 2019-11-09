<?php

declare(strict_types=1);


namespace Sigma\Contract;

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
