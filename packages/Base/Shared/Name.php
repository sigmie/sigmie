<?php

declare(strict_types=1);

namespace Sigmie\Base\Shared;

trait Name
{
    protected string $name;

    public function name(): string
    {
        return $this->name;
    }
}
