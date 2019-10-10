<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Get as GetAction;
use Ni\Elastic\Contract\Subscribable;
use Ni\Elastic\Element;
use Ni\Elastic\Index\Index;
use Ni\Elastic\Index\IndexCollection;

class Get implements GetAction, Subscribable
{
    public function result(array $response): Element
    {
        $collection = new IndexCollection([]);

        foreach ($response as $identifier => $payload) {
            $collection[] = new Index($identifier);
        }

        return $collection->first();
    }

    public function beforeEvent(): string
    {
        return 'before.index.get';
    }

    public function afterEvent(): string
    {
        return 'after.index.get';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }
}
