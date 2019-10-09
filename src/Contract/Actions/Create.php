<?php

namespace Ni\Elastic\Contract\Actions;

use Ni\Elastic\Contract\Action;
use Ni\Elastic\Element;

interface Create extends Action
{
    public function result(array $response): bool;
}
