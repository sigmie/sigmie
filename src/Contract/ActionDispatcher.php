<?php

namespace Ni\Elastic\Contract;

/**
 * Action dispatcher contract
 */
interface ActionDispatcher
{
    /**
     * Dispatch method
     *
     * @param array $data
     * @param Action $action
     *
     * @return array
     */
    public function dispatch(array $data, Action $action): array;
}
