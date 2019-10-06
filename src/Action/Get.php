<?php

namespace Ni\Elastic\Action;

use Ni\Elastic\Action\Action;
use Ni\Elastic\Collection;

interface Get extends Action
{
    public function response($response): Collection;
}
