<?php

namespace Ni\Elastic\Response;

use Ni\Elastic\ElementList;

class ListResponse extends ElementList implements SuccessResponse,
{
    private $list = [];

    public function __construct(array $elements)
    {
        $this->list = $elements;
    }
}
