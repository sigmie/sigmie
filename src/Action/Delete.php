<?php

namespace Ni\Elastic\Action;

interface Delete extends Action
{
    public function response($response): bool;
}
