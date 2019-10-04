<?php

namespace Ni\Elastic\Response\Index;

use Ni\Elastic\Response\SuccessResponse;

class IndexSuccessResponse implements SuccessResponse
{
    private $acknowledged;

    private $element = null;

    public function __construct(bool $acknowledged)
    {
        $this->acknowledged =  $acknowledged;
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged;
    }

    public function error(): bool
    {
        return false;
    }

    /**
     * Get the value of element
     */
    public function getElement()
    {
        return $this->element;
    }

    /**
     * Set the value of element
     *
     * @return  self
     */
    public function setElement($element)
    {
        $this->element = $element;

        return $this;
    }
}
