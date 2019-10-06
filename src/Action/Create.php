<?php

namespace Ni\Elastic\Action;

use Ni\Elastic\Element;

interface Create extends Action
{
    public function response(array $response);
}
