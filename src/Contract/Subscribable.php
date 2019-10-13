<?php

namespace Ni\Elastic\Contract;

/**
 * Subscribable contract
 */
interface Subscribable
{
    /**
     * Before event method
     *
     * @return string
     */
    public function beforeEvent(): string;

    /**
     * After event method
     *
     * @return string
     */
    public function afterEvent(): string;
}
