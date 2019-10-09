<?php

namespace Ni\Elastic\Index\Actions;

use Ni\Elastic\Contract\Actions\Remove as RemoveAction;

class Remove implements RemoveAction
{
    public function result(array $response): bool
    {
        return $response['acknowledged'];
    }
}
