<?php

namespace Sigma\Contract;

use Symfony\Component\EventDispatcher\GenericEvent;

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
    public function preEvent(): string;

    /**
     * After event method
     *
     * @return string
     */
    public function postEvent(): string;
}
