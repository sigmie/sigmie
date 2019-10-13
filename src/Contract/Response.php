<?php

namespace Ni\Elastic\Contract;

use Ni\Elastic\Collection;
use Ni\Elastic\Element;

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
    public function result(array $raw);
}
