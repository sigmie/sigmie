<?php

namespace Sigmie\Base\Contracts;

interface Priority
{
    public function setPriority(int $priority): void;

    public function getPriority(): int;
}
