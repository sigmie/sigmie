<?php

namespace Ni\Elastic\Action;

use Ni\Elastic\Collection;

interface Listing extends Action
{
    public function response($response): Collection;
}
