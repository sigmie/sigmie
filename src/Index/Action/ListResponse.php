<?php

namespace Ni\Elastic\Index\Action;

use Ni\Elastic\Action\Listing;
use Ni\Elastic\Collection;
use Ni\Elastic\Index\IndexCollection;

class ListResponse implements Listing
{
    public function response($response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
