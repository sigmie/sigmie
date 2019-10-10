<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Listing as ListingAction;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Subscribable;
use Ni\Elastic\Index\IndexCollection;

class Listing implements ListingAction, Subscribable
{
    public function result(array $response): Collection
    {
        $response = new IndexCollection($response);

        return $response;
    }

    public function beforeEvent(): string
    {
        return 'before.index.listing';
    }

    public function afterEvent(): string
    {
        return 'after.index.listing';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data,
        ];

        return $params;
    }
}
