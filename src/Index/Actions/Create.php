<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Create as CreateAction;
use Ni\Elastic\Contract\Subscribable;
use Ni\Elastic\Index\Index;

class Create implements CreateAction, Subscribable
{
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }

    public function beforeEvent(): string
    {
        return 'before.index.create';
    }

    public function afterEvent(): string
    {
        return 'after.index.create';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data->getIdentifier()
        ];

        return $params;
    }
}
