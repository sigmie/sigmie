<?php

declare(strict_types=1);


namespace Sigma\Contract;

use Sigma\Collection;
use Sigma\Element;

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
