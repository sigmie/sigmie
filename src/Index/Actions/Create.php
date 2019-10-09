<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Create as CreateAction;
use Ni\Elastic\Index\Index;

class Create implements CreateAction
{
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }

    public function before(): string
    {
        return 'before.index.create';
    }

    public function after(): string
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
