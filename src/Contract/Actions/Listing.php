<?php

namespace Ni\Elastic\Contract\Actions;

use Ni\Elastic\Collection;
use Ni\Elastic\Contract\Action;

interface Listing extends Action
{
    public function result(array $response): Collection;
}