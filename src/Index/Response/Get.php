<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Contract\Response\Get as GetResponse;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\IndexCollection;

class Get implements GetResponse
{
    public function result(array $response): Element
    {
        $collection = new IndexCollection([]);

        foreach (array_keys($response) as $identifier) {
            $collection[] = new Index($identifier);
        }

        return $collection->first();
    }
}
