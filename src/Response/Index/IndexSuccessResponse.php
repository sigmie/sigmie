<?php

namespace Ni\Elastic\Response\Index;

use Ni\Elastic\Response\SuccessResponse;

class IndexSuccessResponse implements SuccessResponse
{
    private $acknowledged;

    public function __construct(bool $acknowledged)
    {
        $this->acknowledged =  $acknowledged;
    }
    public function isAcknowledged(): bool
    { 
        return $this->acknowledged ;
    }

    public function hasError(): bool
    {
        return false;
    }
    // public function shardsAcknowledged(): ?bool;
}
