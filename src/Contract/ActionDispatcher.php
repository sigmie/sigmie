<?php

namespace Sigma\Contract;

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
    public function dispatch(Action $action, array ...$data): array;
}
