<?php

namespace Ni\Elastic\Index\Response;

use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Response;
use Ni\Elastic\Contract\Response\Listing as ListingResponse;
use Ni\Elastic\Index\IndexCollection;

class Listing implements Response
{
    public function result(array $response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
