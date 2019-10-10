<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Response\Listing as ListingResponse;
use Ni\Elastic\Index\IndexCollection;

class Listing implements ListingResponse
{
    public function result(array $response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
