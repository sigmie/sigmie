<?php

namespace Ni\Elastic\Contract;

/**
 * Jsonable contract
 */
interface Jsonable
{
    /**
     * To JSON method
     *
     * @return string
     */
    public function toJSON():string;
}
