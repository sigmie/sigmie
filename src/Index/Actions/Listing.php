<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Listing as ListingAction;
use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Subscribable;
use Elasticsearch\Client as Elasticsearch;
use Ni\Elastic\Contract\Action;
use Ni\Elastic\Index\IndexCollection;

class Listing implements Action, Subscribable
{
    public function execute(Elasticsearch $elasticsearch, array $params): array
    {
        return $elasticsearch->cat()->indices($params);
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
