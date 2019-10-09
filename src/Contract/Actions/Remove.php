<?php

namespace Ni\Elastic\Contract\Actions;

use Ni\Elastic\Contract\Action;

interface Remove extends Action
{
    public function result(array $response): bool;
}
