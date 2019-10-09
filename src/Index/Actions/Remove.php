<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Remove as RemoveAction;

class Remove implements RemoveAction
{
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }

    public function before(): string
    {
        return 'before.index.remove';
    }

    public function after(): string
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
