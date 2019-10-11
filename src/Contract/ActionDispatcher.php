<?php

namespace Ni\Elastic\Contract;

interface ActionDispatcher
{
    public function dispatch($data, Action $action): array;
}
