<?php

namespace Sigma\Index\Response;

use Sigma\Collection;
use Sigma\Contract\Response;
use Sigma\Index\IndexCollection;

class Listing implements Response
{
    /**
     * Create and return an IndexCollection
     *
     * @param array $response
     *
     * @return IndexCollection
     */
    public function result(array $response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
