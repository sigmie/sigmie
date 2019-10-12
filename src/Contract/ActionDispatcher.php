<?php

namespace Ni\Elastic\Contract;

/**
 * Action dispatcher contract
 */
interface ActionDispatcher
{
    public function dispatch($data, Action $action): array;
}
