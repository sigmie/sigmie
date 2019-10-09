<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Create as CreateAction;
use Ni\Elastic\Index\Index;

class Create implements CreateAction
{
    public function result(array $response)
    {
        $index = new Index($response['index']);
        $index->setCreated(true);

        return $index;
    }
}
