<?php

namespace Sigma\Index\Response;

use Sigma\Contract\Response;
use Sigma\Element;
use Sigma\Index\Index;
use Sigma\Index\IndexCollection;

class Get implements Response
{
    /**
     * Return the first element found
     *
     * @param array $response
     *
     * @return Element
     */
    public function result($response): Element
    {
        $collection = new IndexCollection([]);

        foreach (array_keys($response) as $identifier) {
            $collection[] = new Index($identifier);
        }

        return $collection->first();
    }
}
