<?php

declare(strict_types=1);


namespace Sigma\Contract;

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
