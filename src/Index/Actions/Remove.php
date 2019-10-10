<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Remove as RemoveAction;
use Ni\Elastic\Contract\Subscribable;

class Remove implements RemoveAction, Subscribable
{
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }

    public function beforeEvent(): string
    {
        return 'before.index.remove';
    }

    public function afterEvent(): string
    {
        return 'after.index.remove';
    }

    public function prepare($data): array
    {
        $params = [
            'index' => $data
        ];

        return $params;
    }
}
