<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Collection;
use Ni\Elastic\Element;

/**
 * Factory contract
 */
interface Factory
{
    /**
     * Create method
     *
     * @return Element|Collection
     */
    public function create();
}
