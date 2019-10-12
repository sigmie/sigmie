<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\Response\Listing as ListingResponse;
use Ni\Elastic\Index\IndexCollection;

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
