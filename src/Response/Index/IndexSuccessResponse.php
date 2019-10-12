<?php

namespace Ni\Elastic\Response\Index;

use Ni\Elastic\Response\SuccessResponse;

class IndexSuccessResponse implements SuccessResponse
{
    private $acknowledged;

    private $element = null;

    private $list = [];

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

    /**
     * Get the value of list
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * Set the value of list
     *
     * @return  self
     */
    public function setList($list)
    {
        $this->list = $list;

        return $this;
    }
}
