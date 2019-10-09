<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Listing as ListingAction;
use Ni\Elastic\Collection;
use Ni\Elastic\Index\IndexCollection;

class Listing implements ListingAction
{
    public function result(array $response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }
}
