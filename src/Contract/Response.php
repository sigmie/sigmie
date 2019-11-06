<?php

namespace Sigma\Contract;

use Closure;
use Sigma\ActionDispatcher;
use Sigma\Collection;
use Sigma\Element;

/**
 * Response contract
 */
interface Response
{
    /**
     * Result formating method
     *
     * @param array $raw
     *
     * @return bool|Element|Collection
     */
    public function result($data, Closure $boot);
}
