<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Action\Get;
use Ni\Elastic\Collection;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\IndexCollection;

class GetResponse implements Get
{
    public function response($response): Collection
    {
        $collection = new IndexCollection([]);

        foreach ($response as $identifier => $payload) {
            $collection[] = new Index($identifier);
        }

        return $collection;
    }
}
