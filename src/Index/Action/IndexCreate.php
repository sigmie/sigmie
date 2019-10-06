<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Action\Create;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;

class IndexCreate implements Create
{
    public function response(array $response)
    {
        $index = new Index($response['index']);
        $index->setCreated(true);

        return $index;
    }
}
