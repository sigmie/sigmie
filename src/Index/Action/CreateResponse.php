<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Action\Create;
use Ni\Elastic\Contract\Response;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;

class CreateResponse implements Response
{
    public function result(array $response)
    {
        $index = new Index($response['index']);
        $index->setCreated(true);

        return $index;
    }
}
